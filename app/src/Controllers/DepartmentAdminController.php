<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Models\UserRepository;
use Models\AiRepository;
use Models\DepartmentRepository;
use Models\ResourceRepository;
use Services\ResearcherAuthorizationService;

/**
 * Department-administrator console.
 *
 * Every action is gated by requireRole('department_admin'), which renders
 * the 403 page for any other visitor. Actions are scoped to the admin's own
 * department via currentDepartmentId().
 */
class DepartmentAdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('department_admin');

        $pdo = Database::getConnection();
        $departmentId = $this->currentDepartmentId();
        $userRepository = new UserRepository($pdo);
        $authorizations = new ResearcherAuthorizationService($pdo);

        $this->render('pages/department_admin/dashboard', [
            'titrePage'          => 'Administration',
            'user'               => $this->currentUser(),
            'department'         => (new PlaceRepository($pdo))->departmentWithPlace($departmentId),
            'pendingResearchers' => $authorizations->listPending($departmentId),
            'departmentMembers'  => $userRepository->listDepartmentMembers($departmentId),
            'researchers'        => $userRepository->listAuthorizedResearchers($departmentId),
            'revokedResearchers' => $authorizations->listRevoked($departmentId),
        ]);
    }

    public function approveResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $departmentId = $this->currentDepartmentId();
        $service = new ResearcherAuthorizationService(Database::getConnection());
        $result = $service->approve($researcherId, $departmentId, (int) $this->currentUser()['id']);

        // Approving moves the request into the "authorized researchers" table.
        $this->respond($result['success'],
            $result['success'] ? 'Demande chercheur validee.' : $result['error'],
            $result['success']
                ? $this->researcherRowPayload($service, $researcherId, $departmentId, 'authorized')
                : []);
    }

    public function rejectResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->reject($researcherId, $this->currentDepartmentId(), (int) $this->currentUser()['id']);

        // Rejecting just drops the request: no target list (target stays null).
        $this->respond($result['success'],
            $result['success'] ? 'Demande chercheur refusee.' : $result['error']);
    }

    /**
     * Activates or deactivates a teacher/student of the department. Scoped to
     * members only — researchers are handled by revokeResearcher (their global
     * account state is the super admin's lever, not a department admin's).
     */
    public function setUserActive(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $targetId = (int) $this->input('user_id');
        $activate = $this->input('activate') === '1';
        $departmentId = $this->currentDepartmentId();

        if ($targetId === (int) $this->currentUser()['id']) {
            $this->respond(false, 'Vous ne pouvez pas modifier votre propre compte.');
        }

        $userRepository = new UserRepository(Database::getConnection());

        // Scope guard: only act on members of this department, so a forged
        // user_id cannot reach accounts outside the department (IDOR).
        if (!$userRepository->isDepartmentMember($targetId, $departmentId)) {
            $this->respond(false, 'Utilisateur introuvable dans votre departement.');
        }

        $changed = $activate
            ? $userRepository->reactivate($targetId)
            : $userRepository->deactivate($targetId);

        if ($changed === 0) {
            $this->respond(false, 'Aucune modification effectuee.');
        }

        // Re-render the row server-side instead of rebuilding the markup.
        $member = $userRepository->findMemberRow($targetId, $departmentId);
        $this->respond(true,
            $activate ? 'Compte reactive.' : 'Compte desactive.',
            $member === null ? [] : [
                'user_id' => $targetId,
                'row'     => $this->capturePartial('partials/department_admin/member_row',
                    ['member' => $member, 'currentUserId' => (int) $this->currentUser()['id']]),
            ]);
    }

    /**
     * Revokes a researcher's access to this department. Does not touch the
     * researcher's user account (global is_active) — a department admin only
     * controls access to its own department; account state is the super
     * admin's responsibility.
     */
    public function revokeResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $departmentId = $this->currentDepartmentId();
        $service = new ResearcherAuthorizationService(Database::getConnection());
        $result = $service->revoke($researcherId, $departmentId, (int) $this->currentUser()['id']);

        $this->respond($result['success'],
            $result['success'] ? 'Acces chercheur revoque.' : $result['error'],
            $result['success']
                ? $this->researcherRowPayload($service, $researcherId, $departmentId, 'revoked')
                : []);
    }

    /**
     * Re-grants access to a researcher previously revoked on this department.
     * Relies on the kept history; no new request from the researcher is needed.
     */
    public function reauthorizeResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $departmentId = $this->currentDepartmentId();
        $service = new ResearcherAuthorizationService(Database::getConnection());
        $result = $service->reauthorize($researcherId, $departmentId, (int) $this->currentUser()['id']);

        $this->respond($result['success'],
            $result['success'] ? 'Acces chercheur retabli.' : $result['error'],
            $result['success']
                ? $this->researcherRowPayload($service, $researcherId, $departmentId, 'authorized')
                : []);
    }

    /**
     * Builds the AJAX payload for a researcher action: the id, the target
     * mode, and the server-rendered <tr> for that mode (single source of truth
     * for the markup, so the front just inserts it into the right table).
     *
     * @return array{researcher_id:int, target:string, row:string}
     */
    private function researcherRowPayload(
        ResearcherAuthorizationService $service,
        int $researcherId,
        int $departmentId,
        string $mode
    ): array {
        $row = $service->findRow($researcherId, $departmentId);

        return [
            'researcher_id' => $researcherId,
            'target'        => $mode,
            'row'           => $row === null
                ? ''
                : $this->capturePartial('partials/department_admin/researcher_row', ['researcher' => $row, 'mode' => $mode]),
        ];
    }

    /**
     * Replies to an admin action: JSON for an AJAX caller (no reload), or a
     * flash + redirect to /department-admin for a classic form post (graceful
     * fallback when JS is off). On error, the JSON response carries a 422 status.
     *
     * @param array<string, mixed> $data
     */
    private function respond(bool $success, string $message, array $data = []): never
    {
        if ($this->wantsJson()) {
            $this->json(['success' => $success, 'message' => $message] + $data,
                $success ? 200 : 422);
        }

        $this->flash($success ? 'success' : 'error', $message);
        $this->redirect('/department-admin');
    }

    /** The department this admin is scoped to; 403 if none (role implies one). */
    protected function currentDepartmentId(): int
    {
        $departmentId = $this->currentUser()['department_id'] ?? null;
        if ($departmentId === null) {
            $this->renderForbidden();
        }
        return $departmentId;
    }
    public function fromModel(){
        $this->requireAuth();

        $userId = $_SESSION["user_id"];
        $pdo = Database::getConnection();   
        $user = $this->currentUser();

        // recover all types of api who is available
        $Ai = new AiRepository($pdo);
        $adapters = $Ai->getAllTypeOfAdapters();

        // recover departements this admin can manage
        $department = new DepartmentRepository($pdo);
        $departments = $department->getDepartementFromUserId($userId);

        // recover resources this teacher has
        $resource = new ResourceRepository($pdo);
        $resources = $resource->getResourcesFromUserId($userId);

        $this->render('pages/admin/formAddModel', [
            'user'         => $user,
            'adapters'     => $adapters,
            'departments'  => $departments,
            'resources'    => $resources
        ]);
    }

    public function addModel(){
        // Extraction et nettoyage des données reçues du formulaire
        $name          = $this->input('name', null);
        $version       = $this->input('version', null);
        $provider      = $this->input('provider', null);
        $adapter       = $this->input('adapter', null); 
        $apiUrl        = $this->input('api_url', null);
        $maxTokens     = $this->input('max_tokens', null);
        $contextWindow = $this->input('context_window', null);
        
        // Récupère "1" (Oui) ou "0" (Non) depuis le groupe radio 'is_shareable'
        $isShareable   = $this->input('is_shareable', '0');
        $user = $this->currentUser();
        if ($isShareable === '1'){
            $department_id = $user["department_id"];
            $resource_id = null;
        }else {
            $department_id = null;
            $resource_id = $this->input('resource_id', null);
        }
        // Exemple de var_dump pour valider la bonne réception
        try {
            $pdo = Database::getConnection();   
            $Ai = new AiRepository($pdo);
            $result=$Ai->addModel(
                $department_id,
                $resource_id,
                $name,
                $version,
                $provider,
                $adapter,
                $apiUrl,
                (int) $maxTokens,
                (int) $contextWindow,
                $isShareable
            );
            if ($result !=null) {
                $this->flash('success', "Le modèle a été ajouté avec succès.");
                $this->redirect('/chat');  
            }else{
                throw new \Exception("Erreur lors de l'insertion en base de données.");            
            }
        }catch (\Throwable $e){
            $this->flash('error', "Impossible d'ajouter le modèle : " . $e->getMessage());
            $this->redirect('/chat');
        }
    }
}

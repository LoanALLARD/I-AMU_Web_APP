<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Models\DepartmentRepository;
use Models\ResourceRepository;
use Models\AiRepository;
use Services\ResearcherAuthorizationService;

/**
 * Department-administrator console.
 *
 * Every action is gated by requireRole('department_admin'), which renders
 * the 403 page for any other visitor. Actions are scoped to the admin's own
 * department via currentDepartmentId().
 */
class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('department_admin');

        $pdo = Database::getConnection();
        $departmentId = $this->currentDepartmentId();

        $this->render('pages/admin/dashboard', [
            'titrePage'          => 'Administration',
            'user'               => $this->currentUser(),
            'department'         => (new PlaceRepository($pdo))->departmentWithPlace($departmentId),
            'pendingResearchers' => (new ResearcherAuthorizationService($pdo))->listPending($departmentId),
        ]);
    }

    public function approveResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->approve($researcherId, $this->currentDepartmentId(), (int) $this->currentUser()['id']);

        $this->flash($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande chercheur validee.' : $result['error']);
        $this->redirect('/admin');
    }

    public function rejectResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->reject($researcherId, $this->currentDepartmentId(), (int) $this->currentUser()['id']);

        $this->flash($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande chercheur refusee.' : $result['error']);
        $this->redirect('/admin');
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
        $size          = $this->input('size', null);
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
                $size,
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
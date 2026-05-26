<div class="page-container">
    <div class="page-header">
        <h1>Gestion des utilisateurs</h1>
    </div>

    <div class="admin-nav">
        <a href="/admin" class="admin-nav-item">Dashboard</a>
        <a href="/admin/users" class="admin-nav-item active">Utilisateurs</a>
        <a href="/admin/models" class="admin-nav-item">Modèles LLM</a>
        <a href="/admin/config" class="admin-nav-item">Configuration</a>
    </div>

    <!-- Barre de recherche -->
    <form method="GET" action="/admin/users" class="search-bar">
        <input type="text" name="search" placeholder="Rechercher par nom ou email..."
               value="<?= htmlspecialchars($search) ?>" class="search-input">
        <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
    </form>

    <p class="text-muted"><?= $total ?> utilisateur(s) au total</p>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôles</th>
                    <th>Inscrit le</th>
                    <th>Actif</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $hasAnyRole = $u['is_student'] || $u['is_teacher'] || $u['is_researcher'] || $u['is_admin'];
                ?>
                    <tr id="user-row-<?= $u['user_id'] ?>">
                        <td><?= $u['user_id'] ?></td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <div class="roles-cell-wrap">
                                <?php if ($u['is_student']):    ?><span class="badge badge-course">Étudiant</span><?php endif; ?>
                                <?php if ($u['is_teacher']):    ?><span class="badge badge-free">Enseignant</span><?php endif; ?>
                                <?php if ($u['is_specialised']):?><span class="badge badge-specialised">Spécialisé</span><?php endif; ?>
                                <?php if ($u['is_researcher']): ?><span class="badge badge-exam">Chercheur</span><?php endif; ?>
                                <?php if ($u['is_admin']):      ?><span class="badge badge-admin">Admin</span><?php endif; ?>
                                <?php if (!$hasAnyRole):        ?><span class="badge badge-none">Aucun</span><?php endif; ?>
                            </div>
                        </td>
                        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td><?= $u['is_active'] ? icon('check', 'text-success') : icon('x', 'text-error') ?></td>
                        <td class="cell-top">
                            <div class="role-actions">
                                <button class="role-toggle <?= $u['is_student'] ? 'on' : '' ?>"
                                        onclick="toggleRole(<?= $u['user_id'] ?>, 'student', '<?= $u['is_student'] ? 'remove' : 'add' ?>')">
                                    Étudiant
                                </button>
                                <button class="role-toggle <?= $u['is_teacher'] ? 'on' : '' ?>"
                                        onclick="toggleRole(<?= $u['user_id'] ?>, 'teacher', '<?= $u['is_teacher'] ? 'remove' : 'add' ?>')">
                                    Enseignant
                                </button>
                                <button class="role-toggle <?= $u['is_specialised'] ? 'on' : '' ?>"
                                        onclick="toggleRole(<?= $u['user_id'] ?>, 'teacher_specialised', '<?= $u['is_specialised'] ? 'remove' : 'add' ?>')">
                                    Spécialisé
                                </button>
                                <button class="role-toggle <?= $u['is_researcher'] ? 'on' : '' ?>"
                                        onclick="toggleRole(<?= $u['user_id'] ?>, 'researcher', '<?= $u['is_researcher'] ? 'remove' : 'add' ?>')">
                                    Chercheur
                                </button>
                                <button class="role-toggle <?= $u['is_admin'] ? 'on' : '' ?>"
                                        onclick="toggleRole(<?= $u['user_id'] ?>, 'admin', '<?= $u['is_admin'] ? 'remove' : 'add' ?>')">
                                    Admin
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total > $perPage): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                <a href="/admin/users?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                   class="pagination-item <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
async function toggleRole(userId, role, action) {
    try {
        const response = await fetch('/admin/users/role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ user_id: userId, role, action }),
        });
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de la mise à jour du rôle.');
        }
    } catch (err) {
        alert('Erreur réseau.');
    }
}
</script>

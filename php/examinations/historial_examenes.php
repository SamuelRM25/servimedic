<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();
$page_title = "Historial de Exámenes";
include_once '../../includes/header.php';

$limit = 20; // Registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $limit : 0;

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener total de registros
    $stmt_count = $conn->query("SELECT COUNT(*) as total FROM examenes_realizados");
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    // Obtener exámenes paginados
    $stmt = $conn->prepare("
        SELECT id_examen_realizado, nombre_paciente, tipo_examen, cobro, fecha_examen 
        FROM examenes_realizados 
        ORDER BY fecha_examen DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $examenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $examenes = [];
    $total_paginas = 1;
    $error_message = "Error de conexión: " . $e->getMessage();
}
?>

<div class="d-flex">
    <div class="main-content flex-grow-1 p-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-clock-history me-2"></i>Historial de Exámenes</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Regresar
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mb-4"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Paciente</th>
                                    <th>Examen</th>
                                    <th>Cobro (Q)</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($examenes)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No se encontraron registros de exámenes</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($examenes as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['nombre_paciente']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['tipo_examen']); ?></td>
                                            <td>Q<?php echo number_format($exam['cobro'], 2); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($exam['fecha_examen'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de exámenes">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i> Anterior
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                $inicio = max(1, $page - 2);
                                $fin = min($total_paginas, $page + 2);
                                
                                for ($i = $inicio; $i <= $fin; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            Siguiente <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <div class="text-center text-muted mt-2">
                        Mostrando <?php echo count($examenes); ?> de <?php echo $total_registros; ?> registros
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
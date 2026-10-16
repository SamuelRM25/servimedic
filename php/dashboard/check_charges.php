<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'html' => '']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Get Count
    $stmt = $conn->query("SELECT COUNT(*) FROM ordenes_cobro WHERE estado = 'Pendiente'");
    $count = $stmt->fetchColumn();

    // 2. Get Data for HTML
    $stmt = $conn->query("
        SELECT o.*, c.nombre as nombre_cliente
        FROM ordenes_cobro o
        JOIN clientes c ON o.id_cliente = c.id_cliente
        WHERE o.estado = 'Pendiente'
        ORDER BY o.fecha_creacion DESC
    ");
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Generate HTML
    $html = '';
    if (empty($cobros)) {
        $html = '
            <div class="empty-state" style="padding: 1.5rem;">
                <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                <p>No hay cobros pendientes</p>
            </div>';
    } else {
        foreach ($cobros as $cobro) {
            $html .= '
            <div class="queue-card">
                <div class="queue-time" style="color: var(--color-orange);">
                    Q' . number_format($cobro['monto'], 2) . '
                </div>
                <div class="queue-details">
                    <h4>' . htmlspecialchars($cobro['nombre_cliente']) . '</h4>
                    <p>' . htmlspecialchars($cobro['tipo_cobro']) . '</p>
                </div>
                <div class="queue-status">
                    <button class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;"
                            onclick="processCharge(' . $cobro['id_orden'] . ')">
                        Realizar Cobro
                    </button>
                </div>
            </div>';
        }
    }

    echo json_encode(['count' => (int)$count, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['count' => 0, 'html' => '']);
}
?>

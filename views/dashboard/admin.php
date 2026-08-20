<?php
// ============================================================
//  DisneyStock — Vista: Dashboard Principal
//  Archivo: views/dashboard/admin.php
//
//  RESPONSABILIDAD:
//  Muestra el panel de control con métricas y resúmenes.
//  Esta vista NO accede a la BD — solo renderiza variables
//  preparadas por DashboardController.php.
//
//  VARIABLES QUE ESPERA:
//  - $ventasHoy       → Array con 'total' (count) y 'monto' (suma).
//  - $ingresosMes     → Float con ingresos del mes actual.
//  - $ventasRecientes → Array de las últimas 6 ventas.
//  - $totalProductos  → Int con total de productos activos.
//  - $stockBajo       → Int con productos bajo stock mínimo.
//  - $topProductos    → Array con top 5 productos más vendidos.
//  - $alertasStock    → Array con hasta 5 alertas activas.
//
//  SECCIONES DE LA VISTA:
//  1. Encabezado      → Título, fecha, botón acceso rápido a ventas.
//  2. Tarjetas métricas → Ventas hoy, ingresos mes, productos, stock bajo.
//  3. Tabla ventas recientes → Últimas 6 ventas con estado y total.
//  4. Panel alertas   → Alertas activas de stock bajo (máx. 5).
//  5. Top productos   → Ranking de los 5 más vendidos.
//
//  FUNCIÓN AUXILIAR:
//  - fmt(float $n) → Formatea número como moneda colombiana ($1.000).
//
//  ACCESO DIRECTO:
//  Si se accede sin $ventasHoy definido, redirige al controller.
// ============================================================

if (!isset($ventasHoy)) {
    header("Location: /DisneyStock/controllers/DashboardController.php");
    exit;
}

$rol = $_SESSION['usuario']['rol'];

function fmt(float $n): string {
    return '$' . number_format($n, 0, ',', '.');
}
?>

<!-- ── Encabezado ── -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="font-size:1.6rem; font-weight:800; color:#4A1D96; font-family:'Outfit',sans-serif;">
            Panel de Control
        </h1>
        <p style="color:#6B7280; font-size:0.9rem; margin-top:4px;">
            <?= date('l, d \d\e F \d\e Y') ?> &nbsp;·&nbsp; Bienvenido, <?= htmlspecialchars($_SESSION['usuario']['nombre']) ?>
        </p>
    </div>
    <?php if (in_array($rol, ['admin', 'empleado'])): ?>
    <a href="/DisneyStock/controllers/VentaViewController.php"
       style="background:linear-gradient(135deg,#7C3AED,#3B82F6); color:#fff; padding:10px 20px; border-radius:8px; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:8px; text-decoration:none; box-shadow:0 4px 12px rgba(124,58,237,0.3);">
        <i class="fas fa-plus"></i> Nueva Venta
    </a>
    <?php endif; ?>
</div>

<!-- ── Tarjetas de métricas ── -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-bottom:28px;">

    <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:0.8rem; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Ventas Hoy</div>
            <div style="font-size:2rem; font-weight:800; color:#4A1D96;"><?= (int)$ventasHoy['total'] ?></div>
            <div style="font-size:0.85rem; color:#10B981; margin-top:4px; font-weight:600;"><?= fmt((float)$ventasHoy['monto']) ?></div>
        </div>
        <div style="width:52px; height:52px; background:#EDE9FE; border-radius:12px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-cash-register" style="color:#7C3AED; font-size:1.4rem;"></i>
        </div>
    </div>

    <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:0.8rem; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Ingresos del Mes</div>
            <div style="font-size:1.7rem; font-weight:800; color:#4A1D96;"><?= fmt($ingresosMes) ?></div>
            <div style="font-size:0.85rem; color:#6B7280; margin-top:4px;"><?= date('F Y') ?></div>
        </div>
        <div style="width:52px; height:52px; background:#DBEAFE; border-radius:12px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-chart-line" style="color:#3B82F6; font-size:1.4rem;"></i>
        </div>
    </div>

    <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:0.8rem; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Productos</div>
            <div style="font-size:2rem; font-weight:800; color:#4A1D96;"><?= $totalProductos ?></div>
            <div style="font-size:0.85rem; color:#6B7280; margin-top:4px;">Activos en catálogo</div>
        </div>
        <div style="width:52px; height:52px; background:#EDE9FE; border-radius:12px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-box-open" style="color:#7C3AED; font-size:1.4rem;"></i>
        </div>
    </div>

    <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid <?= $stockBajo > 0 ? '#FEF3C7' : '#DDD6FE' ?>; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:0.8rem; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Stock Bajo</div>
            <div style="font-size:2rem; font-weight:800; color:<?= $stockBajo > 0 ? '#D97706' : '#4A1D96' ?>;"><?= $stockBajo ?></div>
            <div style="font-size:0.85rem; color:<?= $stockBajo > 0 ? '#D97706' : '#6B7280' ?>; margin-top:4px; font-weight:<?= $stockBajo > 0 ? '600' : '400' ?>;">
                <?= $stockBajo > 0 ? 'Productos bajo mínimo' : 'Todo en orden' ?>
            </div>
        </div>
        <div style="width:52px; height:52px; background:<?= $stockBajo > 0 ? '#FEF3C7' : '#EDE9FE' ?>; border-radius:12px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-boxes-stacked" style="color:<?= $stockBajo > 0 ? '#D97706' : '#7C3AED' ?>; font-size:1.4rem;"></i>
        </div>
    </div>

</div>

<!-- ── Fila inferior ── -->
<div style="display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start;">

    <!-- Ventas recientes -->
    <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="font-size:1rem; font-weight:700; color:#4A1D96;">
                <i class="fas fa-receipt" style="margin-right:8px; color:#7C3AED;"></i>Ventas Recientes
            </h3>
            <a href="/DisneyStock/controllers/VentaViewController.php" style="font-size:0.85rem; color:#7C3AED; font-weight:600; text-decoration:none;">Ver todas →</a>
        </div>

        <?php if (empty($ventasRecientes)): ?>
        <div style="text-align:center; padding:40px 0; color:#94A3B8;">
            <i class="fas fa-receipt" style="font-size:2.5rem; display:block; margin-bottom:12px; opacity:0.3;"></i>
            <p>No hay ventas registradas aún.</p>
            <?php if (in_array($rol, ['admin','empleado'])): ?>
            <a href="/DisneyStock/controllers/VentaViewController.php" style="display:inline-block; margin-top:12px; background:linear-gradient(135deg,#7C3AED,#3B82F6); color:#fff; padding:8px 18px; border-radius:8px; font-size:0.85rem; text-decoration:none;">Registrar primera venta</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
                <thead>
                    <tr style="border-bottom:2px solid #DDD6FE;">
                        <th style="padding:10px 12px; text-align:left; color:#6B7280; font-weight:600;">Factura</th>
                        <th style="padding:10px 12px; text-align:left; color:#6B7280; font-weight:600;">Vendedor</th>
                        <th style="padding:10px 12px; text-align:right; color:#6B7280; font-weight:600;">Total</th>
                        <th style="padding:10px 12px; text-align:center; color:#6B7280; font-weight:600;">Estado</th>
                        <th style="padding:10px 12px; text-align:left; color:#6B7280; font-weight:600;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ventasRecientes as $v):
                    $ec = match($v['estado']) {
                        'completada' => ['bg'=>'#D1FAE5','color'=>'#065F46'],
                        'anulada'    => ['bg'=>'#FEE2E2','color'=>'#991B1B'],
                        default      => ['bg'=>'#FEF3C7','color'=>'#92400E'],
                    };
                ?>
                <tr style="border-bottom:1px solid #F3F0FF;" onmouseover="this.style.background='#F5F3FF'" onmouseout="this.style.background=''">
                    <td style="padding:12px; font-weight:700; color:#4A1D96;"><?= htmlspecialchars($v['numero_factura'] ?? '—') ?></td>
                    <td style="padding:12px; color:#334155;"><?= htmlspecialchars($v['vendedor'] ?? '—') ?></td>
                    <td style="padding:12px; text-align:right; font-weight:700; color:#4A1D96;"><?= fmt((float)$v['total']) ?></td>
                    <td style="padding:12px; text-align:center;">
                        <span style="background:<?= $ec['bg'] ?>; color:<?= $ec['color'] ?>; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; text-transform:capitalize;">
                            <?= htmlspecialchars($v['estado']) ?>
                        </span>
                    </td>
                    <td style="padding:12px; color:#6B7280; font-size:0.82rem;"><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Panel derecho -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Alertas de stock -->
        <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE;">
            <h3 style="font-size:1rem; font-weight:700; color:#4A1D96; margin-bottom:16px;">
                <i class="fas fa-triangle-exclamation" style="margin-right:8px; color:#D97706;"></i>Alertas de Stock
            </h3>
            <?php if (empty($alertasStock)): ?>
            <div style="text-align:center; padding:20px 0; color:#94A3B8;">
                <i class="fas fa-check-circle" style="font-size:2rem; color:#10B981; display:block; margin-bottom:8px;"></i>
                <p style="font-size:0.88rem;">Todo el inventario está en orden.</p>
            </div>
            <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($alertasStock as $a): ?>
                <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:12px 14px; border-left:4px solid #F59E0B;">
                    <div style="font-weight:700; color:#4A1D96; font-size:0.88rem;"><?= htmlspecialchars($a['nombre']) ?></div>
                    <div style="font-size:0.78rem; color:#6B7280; margin-top:2px;"><?= htmlspecialchars($a['mensaje']) ?></div>
                    <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:0.82rem;">
                        <span style="color:#D97706; font-weight:600;">Stock: <?= $a['stock_actual'] ?></span>
                        <span style="color:#94A3B8;">Mínimo: <?= $a['stock_minimo'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($stockBajo > 5): ?>
                <a href="/DisneyStock/controllers/InventarioViewController.php" style="text-align:center; font-size:0.82rem; color:#7C3AED; font-weight:600; text-decoration:none; padding-top:4px;">Ver todos (<?= $stockBajo ?>)</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top productos -->
        <?php if (!empty($topProductos)): ?>
        <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 10px rgba(74,29,150,0.08); border:1px solid #DDD6FE;">
            <h3 style="font-size:1rem; font-weight:700; color:#4A1D96; margin-bottom:16px;">
                <i class="fas fa-star" style="margin-right:8px; color:#A78BFA;"></i>Top Productos
            </h3>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($topProductos as $i => $p): ?>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:26px; height:26px; background:#EDE9FE; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:800; color:#7C3AED; flex-shrink:0;"><?= $i+1 ?></div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.85rem; font-weight:600; color:#4A1D96; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($p['nombre']) ?></div>
                        <div style="font-size:0.75rem; color:#6B7280;"><?= $p['vendidos'] ?> unidades vendidas</div>
                    </div>
                    <div style="font-size:0.85rem; font-weight:700; color:#059669; flex-shrink:0;"><?= fmt((float)$p['total']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

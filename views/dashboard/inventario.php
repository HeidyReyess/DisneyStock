<?php
// ============================================================
//  DisneyStock - Vista: Inventario
//  Muestra stock actual, movimientos recientes y modal de
//  registro de movimientos. NO accede a la BD directamente.
// ============================================================
if (!isset($inventario)) {
    header("Location: /DisneyStock/controllers/InventarioController.php");
    exit;
}
require_once __DIR__ . '/../../helpers/auth.php';
$filtro = $_GET['filtro'] ?? 'todos';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Inventario</h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;"><?= count($inventario) ?> producto(s) en stock</p>
    </div>
    <button onclick="document.getElementById('modalMovimiento').style.display='flex'" style="background:#4A1D96;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;">
        <i class="fas fa-exchange-alt"></i> Registrar Movimiento
    </button>
</div>

<!-- Banner de alerta cuando hay productos con stock bajo -->
<?php if ($totalBajo > 0): ?>
<div style="background:#F97316;color:#fff;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:12px;">
        <i class="fas fa-triangle-exclamation" style="font-size:1.3rem;"></i>
        <div>
            <div style="font-weight:700;font-size:0.95rem;">&#161;Alerta de Stock Bajo!</div>
            <div style="font-size:0.85rem;opacity:0.9;"><?= $totalBajo ?> producto(s) han alcanzado o superado el l&#237;mite m&#237;nimo de stock.</div>
        </div>
    </div>
    <a href="?filtro=bajo" style="background:rgba(255,255,255,0.2);color:#fff;padding:8px 16px;border-radius:8px;font-weight:700;font-size:0.85rem;text-decoration:none;border:1px solid rgba(255,255,255,0.4);">
        Ver productos afectados &rarr;
    </a>
</div>
<?php endif; ?>

<!-- Filtros rapidos: todos o solo stock bajo -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?filtro=todos" style="padding:8px 18px;border-radius:8px;font-size:0.88rem;font-weight:600;text-decoration:none;background:<?= $filtro==='todos'?'#4A1D96':'#fff' ?>;color:<?= $filtro==='todos'?'#fff':'#6B7280' ?>;border:1.5px solid <?= $filtro==='todos'?'#4A1D96':'#DDD6FE' ?>;">Todos</a>
    <a href="?filtro=bajo" style="padding:8px 18px;border-radius:8px;font-size:0.88rem;font-weight:600;text-decoration:none;background:<?= $filtro==='bajo'?'#D97706':'#fff' ?>;color:<?= $filtro==='bajo'?'#fff':'#6B7280' ?>;border:1.5px solid <?= $filtro==='bajo'?'#D97706':'#DDD6FE' ?>;">
        <i class="fas fa-triangle-exclamation"></i> Stock Bajo
    </a>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

    <!-- Tabla principal de inventario -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <?php if (empty($inventario)): ?>
        <div style="text-align:center;padding:60px 20px;color:#94A3B8;">
            <i class="fas fa-boxes-stacked" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
            <p>No hay productos en inventario.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
            <thead style="background:#F5F3FF;border-bottom:2px solid #DDD6FE;">
                <tr>
                    <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Producto</th>
                    <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Categor&#237;a</th>
                    <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Stock Actual</th>
                    <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">M&#237;nimo</th>
                    <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Estado</th>
                    <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Fecha Ingreso</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inventario as $inv):
                $bajo = $inv['stock_minimo'] > 0 && $inv['stock_actual'] <= $inv['stock_minimo'];
            ?>
            <tr style="border-bottom:1px solid #F3F0FF;<?= $bajo ? 'background:#FFFBEB;' : '' ?>"
                onmouseover="this.style.background='#F5F3FF'"
                onmouseout="this.style.background='<?= $bajo ? '#FFFBEB' : '' ?>'">
                <td style="padding:12px 16px;">
                    <div style="font-weight:600;color:#4A1D96;"><?= htmlspecialchars($inv['nombre']) ?></div>
                    <?php if ($inv['proveedor']): ?>
                    <div style="font-size:0.78rem;color:#94A3B8;">Prov: <?= htmlspecialchars($inv['proveedor']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px;">
                    <span style="background:#EDE9FE;color:#4A1D96;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                        <?= htmlspecialchars($inv['categoria'] ?? '&mdash;') ?>
                    </span>
                </td>
                <!-- Stock resaltado en naranja si esta bajo el minimo -->
                <td style="padding:12px 16px;text-align:center;font-size:1.1rem;font-weight:800;color:<?= $bajo ? '#D97706' : '#4A1D96' ?>;">
                    <?= $inv['stock_actual'] ?>
                </td>
                <td style="padding:12px 16px;text-align:center;color:#6B7280;"><?= $inv['stock_minimo'] ?></td>
                <td style="padding:12px 16px;text-align:center;">
                    <?php if ($bajo): ?>
                    <span style="background:#FEF3C7;color:#92400E;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                        <i class="fas fa-triangle-exclamation"></i> Bajo
                    </span>
                    <?php else: ?>
                    <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                        <i class="fas fa-check"></i> OK
                    </span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px;color:#6B7280;font-size:0.82rem;"><?= date('d/m/Y', strtotime($inv['fecha_ingreso'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Panel lateral: ultimos movimientos de inventario -->
    <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;">
        <h3 style="font-size:1rem;font-weight:700;color:#4A1D96;margin-bottom:16px;">
            <i class="fas fa-history" style="margin-right:8px;color:#7C3AED;"></i>&#218;ltimos Movimientos
        </h3>
        <?php if (empty($movimientos)): ?>
        <p style="text-align:center;color:#94A3B8;padding:20px 0;font-size:0.88rem;">Sin movimientos registrados.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;max-height:500px;overflow-y:auto;">
        <?php foreach ($movimientos as $m):
            $esEntrada = $m['tipo'] === 'entrada';
        ?>
        <!-- Borde verde para entradas, rojo para salidas/ajustes -->
        <div style="border:1px solid #DDD6FE;border-radius:10px;padding:12px;border-left:4px solid <?= $esEntrada ? '#10B981' : '#EF4444' ?>;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-weight:700;color:#4A1D96;font-size:0.85rem;"><?= htmlspecialchars($m['producto']) ?></span>
                <span style="font-weight:800;color:<?= $esEntrada ? '#059669' : '#DC2626' ?>;font-size:0.9rem;"><?= $esEntrada ? '+' : '-' ?><?= $m['cantidad'] ?></span>
            </div>
            <div style="font-size:0.78rem;color:#6B7280;"><?= ucfirst($m['tipo']) ?> &middot; <?= htmlspecialchars($m['motivo'] ?? '&mdash;') ?></div>
            <div style="font-size:0.75rem;color:#94A3B8;margin-top:4px;"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?> &middot; <?= htmlspecialchars($m['usuario'] ?? 'Sistema') ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para registrar movimiento de inventario -->
<div id="modalMovimiento" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:480px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Registrar Movimiento</h2>
            <button onclick="document.getElementById('modalMovimiento').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <form method="POST" action="/DisneyStock/controllers/InventarioController.php">
            <input type="hidden" name="accion" value="movimiento">
            <?php csrfField(); ?>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Producto *</label>
                    <select name="id_producto" id="selMovProducto" required onchange="mostrarStock(this)" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <option value="">Seleccionar producto...</option>
                        <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id_producto'] ?>" data-stock="<?= $p['stock_actual'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Muestra el stock actual del producto seleccionado -->
                    <div id="stockInfo" style="margin-top:6px;font-size:0.82rem;color:#6B7280;min-height:20px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Tipo *</label>
                        <select name="tipo_movimiento" required style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                            <option value="entrada">Entrada</option>
                            <option value="salida">Salida</option>
                            <option value="ajuste">Ajuste</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Cantidad *</label>
                        <input type="number" name="cantidad" required min="1" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Descripci&#243;n</label>
                    <input type="text" name="descripcion" maxlength="255" placeholder="Ej: Compra a proveedor, devoluci&#243;n..." style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalMovimiento').style.display='none'" style="padding:10px 20px;border:1.5px solid #DDD6FE;border-radius:8px;background:#fff;color:#6B7280;font-weight:600;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:10px 24px;background:#4A1D96;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script>
window.onclick = e => { if (e.target.id === 'modalMovimiento') document.getElementById('modalMovimiento').style.display = 'none'; };

// Muestra el stock actual del producto seleccionado en el modal
function mostrarStock(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const info = document.getElementById('stockInfo');
    if (!sel.value) { info.textContent = ''; return; }
    const stock = parseInt(opt.dataset.stock) || 0;
    info.innerHTML = `Stock actual: <strong style="color:${stock <= 0 ? '#DC2626' : '#059669'}">${stock} unidades</strong>`;
}
</script>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>

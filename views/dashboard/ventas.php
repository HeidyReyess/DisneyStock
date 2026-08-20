<?php
// ============================================================
//  DisneyStock - Vista: Ventas
//  Muestra listado de ventas y formulario para nueva venta.
//  NO accede a la BD directamente.
// ============================================================
if (!isset($ventas)) {
    header("Location: /DisneyStock/controllers/VentaController.php");
    exit;
}
require_once __DIR__ . '/../../helpers/auth.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Ventas</h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;"><?= count($ventas) ?> venta(s) en el per&#237;odo</p>
    </div>
    <button onclick="document.getElementById('modalVenta').style.display='flex'" style="background:#4A1D96;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;">
        <i class="fas fa-plus"></i> Nueva Venta
    </button>
</div>

<!-- Filtros de fecha y estado -->
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #DDD6FE;margin-bottom:20px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Desde</label>
        <input type="date" name="desde" value="<?= $desde ?>" style="padding:8px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;">
    </div>
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Hasta</label>
        <input type="date" name="hasta" value="<?= $hasta ?>" style="padding:8px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;">
    </div>
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Estado</label>
        <select name="estado" style="padding:8px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;background:#fff;">
            <option value="">Todos</option>
            <option value="completada" <?= $estado==='completada'?'selected':'' ?>>Completada</option>
            <option value="pendiente"  <?= $estado==='pendiente'?'selected':'' ?>>Pendiente</option>
            <option value="anulada"    <?= $estado==='anulada'?'selected':'' ?>>Anulada</option>
        </select>
    </div>
    <button type="submit" style="background:#7C3AED;color:#fff;padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:0.88rem;">Filtrar</button>
</form>

<!-- Tabla de ventas -->
<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
    <?php if (empty($ventas)): ?>
    <div style="text-align:center;padding:60px 20px;color:#94A3B8;">
        <i class="fas fa-receipt" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
        <p>No hay ventas en este per&#237;odo.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
        <thead style="background:#F5F3FF;border-bottom:2px solid #DDD6FE;">
            <tr>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Factura</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Vendedor</th>
                <th style="padding:12px 16px;text-align:right;color:#6B7280;font-weight:600;">Subtotal</th>
                <th style="padding:12px 16px;text-align:right;color:#6B7280;font-weight:600;">Descuento</th>
                <th style="padding:12px 16px;text-align:right;color:#6B7280;font-weight:600;">Total</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Estado</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Fecha</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Detalle</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ventas as $v):
            $ec = match($v['estado']) {
                'completada' => ['#D1FAE5','#065F46'],
                'anulada'    => ['#FEE2E2','#991B1B'],
                default      => ['#FEF3C7','#92400E']
            };
        ?>
        <tr style="border-bottom:1px solid #F3F0FF;" onmouseover="this.style.background='#F5F3FF'" onmouseout="this.style.background=''">
            <td style="padding:12px 16px;font-weight:700;color:#4A1D96;"><?= htmlspecialchars($v['numero_factura'] ?? '&mdash;') ?></td>
            <td style="padding:12px 16px;color:#6B7280;"><?= htmlspecialchars($v['vendedor'] ?? '&mdash;') ?></td>
            <td style="padding:12px 16px;text-align:right;color:#6B7280;">$<?= number_format($v['subtotal'],0,',','.') ?></td>
            <td style="padding:12px 16px;text-align:right;color:#DC2626;">-$<?= number_format($v['descuento'],0,',','.') ?></td>
            <td style="padding:12px 16px;text-align:right;font-weight:800;color:#4A1D96;">$<?= number_format($v['total'],0,',','.') ?></td>
            <td style="padding:12px 16px;text-align:center;">
                <span style="background:<?= $ec[0] ?>;color:<?= $ec[1] ?>;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;text-transform:capitalize;">
                    <?= $v['estado'] ?>
                </span>
            </td>
            <td style="padding:12px 16px;color:#6B7280;font-size:0.82rem;"><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
            <td style="padding:12px 16px;text-align:center;">
                <button onclick="verDetalle('<?= $v['id_venta'] ?>')" style="background:#EDE9FE;color:#4A1D96;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;">
                    <i class="fas fa-eye"></i>
                </button>
                <?php if ($v['estado'] !== 'anulada' && $_SESSION['usuario']['rol'] === 'admin'): ?>
                <!-- Boton anular solo visible para admin en ventas no anuladas -->
                <button onclick="anularVenta('<?= $v['id_venta'] ?>','<?= htmlspecialchars($v['numero_factura'] ?? $v['id_venta'],ENT_QUOTES) ?>')" style="background:#FEE2E2;color:#991B1B;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;margin-left:4px;">
                    <i class="fas fa-ban"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal nueva venta -->
<div id="modalVenta" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:700px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.25);max-height:92vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Nueva Venta</h2>
            <button onclick="document.getElementById('modalVenta').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <form method="POST" action="/DisneyStock/controllers/VentaController.php" id="formVenta">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="items" id="itemsJSON" value="[]">
            <?php csrfField(); ?>

            <div style="margin-bottom:20px;">
                <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Descuento ($)</label>
                <input type="number" name="descuento" id="descuento" value="0" min="0" step="0.01" oninput="calcularTotales()" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
            </div>

            <!-- Selector de productos para agregar a la venta -->
            <div style="background:#F5F3FF;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #DDD6FE;">
                <h4 style="font-size:0.9rem;font-weight:700;color:#4A1D96;margin-bottom:12px;">Agregar Producto</h4>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <div style="flex:2;min-width:180px;">
                        <select id="selProducto" style="width:100%;padding:9px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;background:#fff;">
                            <option value="">Seleccionar producto...</option>
                            <?php foreach ($productos as $p): ?>
                            <option value="<?= $p['id_producto'] ?>" data-precio="<?= $p['precio_venta'] ?>" data-nombre="<?= htmlspecialchars($p['nombre'],ENT_QUOTES) ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width:90px;">
                        <input type="number" id="cantidadItem" value="1" min="1" placeholder="Cant." style="width:100%;padding:9px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;box-sizing:border-box;">
                    </div>
                    <button type="button" onclick="agregarItem()" style="background:#4A1D96;color:#fff;padding:9px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:0.88rem;white-space:nowrap;">+ Agregar</button>
                </div>
            </div>

            <!-- Lista dinamica de items agregados -->
            <div id="listaItems" style="margin-bottom:16px;min-height:40px;"></div>

            <!-- Panel de totales y boton guardar -->
            <div style="background:#4A1D96;border-radius:10px;padding:16px;color:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;gap:24px;">
                    <div><div style="font-size:0.75rem;opacity:0.7;">Subtotal</div><div style="font-weight:700;" id="lblSubtotal">$0</div></div>
                    <div><div style="font-size:0.75rem;opacity:0.7;">Descuento</div><div style="font-weight:700;color:#F59E0B;" id="lblDescuento">$0</div></div>
                    <div><div style="font-size:0.75rem;opacity:0.7;">TOTAL</div><div style="font-weight:800;font-size:1.3rem;" id="lblTotal">$0</div></div>
                </div>
                <button type="submit" style="background:#F59E0B;color:#4A1D96;padding:12px 28px;border-radius:8px;border:none;cursor:pointer;font-weight:800;font-size:1rem;">Guardar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal detalle de venta — se llena via AJAX -->
<div id="modalDetalle" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:560px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Detalle de Venta</h2>
            <button onclick="document.getElementById('modalDetalle').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <div id="contenidoDetalle" style="color:#334155;font-size:0.9rem;">Cargando...</div>
    </div>
</div>

<script>
let items = [];

// Agrega un producto al carrito en memoria
function agregarItem() {
    const sel  = document.getElementById('selProducto');
    const cant = parseInt(document.getElementById('cantidadItem').value) || 1;
    if (!sel.value) return;
    const opt   = sel.options[sel.selectedIndex];
    const precio = parseFloat(opt.dataset.precio) || 0;
    const idx   = items.findIndex(i => i.id_producto === sel.value);
    if (idx >= 0) { items[idx].cantidad += cant; }
    else { items.push({ id_producto: sel.value, nombre: opt.dataset.nombre, precio_unitario: precio, cantidad: cant }); }
    renderItems();
    sel.value = '';
    document.getElementById('cantidadItem').value = 1;
}

function quitarItem(i) { items.splice(i, 1); renderItems(); }

// Dibuja la lista de items con controles de cantidad
function renderItems() {
    const cont = document.getElementById('listaItems');
    if (!items.length) {
        cont.innerHTML = '<p style="color:#94A3B8;font-size:0.88rem;text-align:center;padding:10px;">Sin productos agregados.</p>';
        calcularTotales();
        return;
    }
    cont.innerHTML = items.map((it, i) => `
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #DDD6FE;border-radius:8px;margin-bottom:8px;background:#fff;">
            <div style="flex:1;font-weight:600;color:#4A1D96;font-size:0.88rem;">${it.nombre}</div>
            <div style="display:flex;align-items:center;gap:6px;">
                <button type="button" onclick="cambiarCant(${i},-1)" style="width:26px;height:26px;border:1px solid #DDD6FE;border-radius:6px;background:#F5F3FF;cursor:pointer;font-weight:700;">-</button>
                <span style="min-width:28px;text-align:center;font-weight:700;">${it.cantidad}</span>
                <button type="button" onclick="cambiarCant(${i},1)" style="width:26px;height:26px;border:1px solid #DDD6FE;border-radius:6px;background:#F5F3FF;cursor:pointer;font-weight:700;">+</button>
            </div>
            <div style="min-width:80px;text-align:right;font-weight:700;color:#059669;">$${fmt(it.precio_unitario * it.cantidad)}</div>
            <button type="button" onclick="quitarItem(${i})" style="background:#FEE2E2;color:#DC2626;border:none;padding:4px 8px;border-radius:6px;cursor:pointer;font-size:0.8rem;">&times;</button>
        </div>`).join('');
    calcularTotales();
}

function cambiarCant(i, delta) { items[i].cantidad = Math.max(1, items[i].cantidad + delta); renderItems(); }

// Recalcula subtotal, descuento y total y actualiza el panel
function calcularTotales() {
    const sub  = items.reduce((s, it) => s + it.precio_unitario * it.cantidad, 0);
    const desc = parseFloat(document.getElementById('descuento').value) || 0;
    const total = Math.max(0, sub - desc);
    document.getElementById('lblSubtotal').textContent = '$' + fmt(sub);
    document.getElementById('lblDescuento').textContent = '-$' + fmt(desc);
    document.getElementById('lblTotal').textContent = '$' + fmt(total);
    document.getElementById('itemsJSON').value = JSON.stringify(items);
}

function fmt(n) { return Math.round(n).toLocaleString('es-CO'); }

// Carga el detalle de la venta via AJAX e inyecta el HTML en el modal
function verDetalle(id) {
    document.getElementById('modalDetalle').style.display = 'flex';
    document.getElementById('contenidoDetalle').innerHTML = '<p style="text-align:center;padding:20px;color:#94A3B8;">Cargando...</p>';
    fetch('/DisneyStock/controllers/VentaController.php?accion=detalle&id=' + id)
        .then(r => r.text())
        .then(html => { document.getElementById('contenidoDetalle').innerHTML = html; });
}

function anularVenta(id, factura) {
    Swal.fire({
        title: '\u00bfAnular venta?',
        text: 'Factura: ' + factura,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'S\u00ed, anular'
    }).then(r => {
        if (r.isConfirmed) window.location = '/DisneyStock/controllers/VentaController.php?accion=anular&id=' + id;
    });
}

// Bloquear envio si no hay items
document.getElementById('formVenta').addEventListener('submit', function(e) {
    if (!items.length) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Sin productos', text: 'Agrega al menos un producto a la venta.', confirmButtonColor: '#4A1D96' });
    }
});

window.onclick = e => {
    if (e.target.id === 'modalVenta')   document.getElementById('modalVenta').style.display = 'none';
    if (e.target.id === 'modalDetalle') document.getElementById('modalDetalle').style.display = 'none';
};

renderItems();
</script>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>

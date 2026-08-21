<?php
// ============================================================
//  DisneyStock - Vista: Productos
//  Muestra tabla de productos y modales para crear/editar/categorias.
//  NO accede a la BD directamente.
// ============================================================
if (!isset($productos)) {
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;
}
require_once __DIR__ . '/../../helpers/auth.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Productos</h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;"><?= count($productos) ?> producto(s)</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="abrirModalCat()" style="background:#EDE9FE;color:#4A1D96;padding:10px 16px;border-radius:8px;font-weight:600;font-size:0.88rem;border:none;cursor:pointer;">
            <i class="fas fa-tags"></i> Categor&#237;as
        </button>
        <button onclick="abrirModal()" style="background:#4A1D96;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>
</div>

<!-- Filtros de busqueda -->
<form method="GET" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;">
    <div style="position:relative;flex:1;min-width:200px;max-width:360px;">
        <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94A3B8;"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar por nombre o codigo..."
               style="width:100%;padding:10px 14px 10px 40px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;box-sizing:border-box;">
    </div>
    <select name="cat" style="padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;color:#334155;">
        <option value="">Todas las categor&#237;as</option>
        <?php foreach ($categorias as $cat): ?>
        <option value="<?= $cat['id_categoria'] ?>" <?= $filtroCategoria == $cat['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="background:#7C3AED;color:#fff;padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:600;">Filtrar</button>
    <?php if ($buscar || $filtroCategoria): ?>
    <a href="/DisneyStock/controllers/ProductoController.php" style="padding:10px 16px;border:1.5px solid #DDD6FE;border-radius:8px;color:#6B7280;text-decoration:none;font-size:0.9rem;display:flex;align-items:center;">Limpiar</a>
    <?php endif; ?>
</form>

<!-- Tabla de productos -->
<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
    <?php if (empty($productos)): ?>
    <div style="text-align:center;padding:60px 20px;color:#94A3B8;">
        <i class="fas fa-box" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
        <p>No hay productos registrados.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
        <thead style="background:#F5F3FF;border-bottom:2px solid #DDD6FE;">
            <tr>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">ID</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Nombre</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Categor&#237;a</th>
                <th style="padding:12px 16px;text-align:right;color:#6B7280;font-weight:600;">Precio Venta</th>
                <th style="padding:12px 16px;text-align:right;color:#6B7280;font-weight:600;">Precio Compra</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Stock</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Estado</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
        <tr style="border-bottom:1px solid #F3F0FF;" onmouseover="this.style.background='#F5F3FF'" onmouseout="this.style.background=''">
            <td style="padding:12px 16px;font-family:monospace;color:#7C3AED;font-weight:600;">#<?= $p['id_producto'] ?></td>
            <td style="padding:12px 16px;font-weight:600;color:#4A1D96;">
                <?= htmlspecialchars($p['nombre']) ?>
                <?php if ($p['proveedor']): ?>
                <div style="font-size:0.78rem;color:#94A3B8;font-weight:400;">Prov: <?= htmlspecialchars($p['proveedor']) ?></div>
                <?php endif; ?>
            </td>
            <td style="padding:12px 16px;">
                <span style="background:#EDE9FE;color:#4A1D96;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                    <?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categor&#237;a') ?>
                </span>
            </td>
            <td style="padding:12px 16px;text-align:right;font-weight:700;color:#059669;">$<?= number_format($p['precio_venta'],0,',','.') ?></td>
            <td style="padding:12px 16px;text-align:right;color:#6B7280;">$<?= number_format($p['precio_compra'],0,',','.') ?></td>
            <td style="padding:12px 16px;text-align:center;">
                <?php
                $bajo  = $p['stock_minimo'] > 0 && $p['stock_actual'] <= $p['stock_minimo'];
                $color = $bajo ? '#D97706' : '#4A1D96';
                ?>
                <span style="font-weight:700;color:<?= $color ?>;"><?= $p['stock_actual'] ?></span>
                <?php if ($bajo): ?>
                <!-- Muestra la flecha y el minimo si el stock esta bajo -->
                <span style="font-size:0.7rem;color:#D97706;display:block;">&#8595; m&#237;n: <?= $p['stock_minimo'] ?></span>
                <?php endif; ?>
            </td>
            <td style="padding:12px 16px;text-align:center;">
                <span style="background:<?= $p['estado']==='activo' ? '#D1FAE5' : '#FEE2E2' ?>;color:<?= $p['estado']==='activo' ? '#065F46' : '#991B1B' ?>;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                    <?= ucfirst($p['estado']) ?>
                </span>
            </td>
            <td style="padding:12px 16px;text-align:center;">
                <button onclick='editarProducto(<?= json_encode($p) ?>)' style="background:#EDE9FE;color:#4A1D96;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;margin-right:4px;"><i class="fas fa-edit"></i></button>
                <button onclick="toggleActivo('<?= $p['id_producto'] ?>','<?= $p['estado'] ?>')" style="background:<?= $p['estado']==='activo' ? '#FEF3C7' : '#D1FAE5' ?>;color:<?= $p['estado']==='activo' ? '#92400E' : '#065F46' ?>;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;">
                    <i class="fas fa-<?= $p['estado']==='activo' ? 'ban' : 'check' ?>"></i>
                </button>
                <button onclick="eliminarProducto('<?= $p['id_producto'] ?>','<?= htmlspecialchars($p['nombre'],ENT_QUOTES) ?>')" style="background:#FEE2E2;color:#991B1B;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;margin-left:4px;"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal preview imagen fullscreen -->
<div id="modalImagen" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:2000;align-items:center;justify-content:center;" onclick="this.style.display='none'">
    <div style="position:relative;max-width:90vw;max-height:90vh;text-align:center;" onclick="event.stopPropagation()">
        <button onclick="document.getElementById('modalImagen').style.display='none'"
                style="position:absolute;top:-16px;right:-16px;background:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:1.2rem;cursor:pointer;color:#4A1D96;font-weight:800;box-shadow:0 2px 8px rgba(0,0,0,0.3);">&times;</button>
        <img id="imgPreview" src="" alt="" style="max-width:80vw;max-height:80vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5);object-fit:contain;">
        <div id="imgNombre" style="color:#fff;margin-top:12px;font-weight:700;font-size:1rem;font-family:'Outfit',sans-serif;"></div>
    </div>
</div>

<!-- Modal crear/editar producto -->
<div id="modalProducto" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:560px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 id="modalTituloP" style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Nuevo Producto</h2>
            <button onclick="cerrarModal('modalProducto')" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <form method="POST" action="/DisneyStock/controllers/ProductoController.php">
            <input type="hidden" name="accion" id="accionP" value="crear">
            <input type="hidden" name="id" id="pId">
            <?php csrfField(); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="grid-column:1/-1;">
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Nombre *</label>
                    <input type="text" name="nombre" id="pNombre" required maxlength="255" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Categor&#237;a</label>
                    <select name="id_categoria" id="pCategoria" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;box-sizing:border-box;">
                        <option value="">Sin categor&#237;a</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Proveedor</label>
                    <input type="text" name="proveedor" id="pProveedor" maxlength="200" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Precio Venta *</label>
                    <input type="number" name="precio_venta" id="pVenta" required min="0" step="0.01" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Precio Compra</label>
                    <input type="number" name="precio_compra" id="pCompra" min="0" step="0.01" value="0" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Stock Inicial</label>
                    <input type="number" name="stock_actual" id="pStock" min="0" value="0" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Stock M&#237;nimo</label>
                    <input type="number" name="stock_minimo" id="pMinimo" min="0" value="0" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Fecha Ingreso</label>
                    <input type="date" name="fecha_ingreso" id="pFecha" value="<?= date('Y-m-d') ?>" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end;">
                <button type="button" onclick="cerrarModal('modalProducto')" style="padding:10px 20px;border:1.5px solid #DDD6FE;border-radius:8px;background:#fff;color:#6B7280;font-weight:600;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:10px 24px;background:#4A1D96;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal categorias -->
<div id="modalCat" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:440px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Categor&#237;as</h2>
            <button onclick="cerrarModal('modalCat')" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <form method="POST" action="/DisneyStock/controllers/CategoriaController.php" style="display:flex;gap:10px;margin-bottom:20px;">
            <input type="hidden" name="accion" value="crear">
            <?php csrfField(); ?>
            <input type="text" name="nombre_categoria" placeholder="Nueva categor&#237;a..." required maxlength="255" style="flex:1;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;">
            <button type="submit" style="background:#4A1D96;color:#fff;padding:10px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:600;">Agregar</button>
        </form>
        <div style="max-height:260px;overflow-y:auto;">
            <?php foreach ($categorias as $cat): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:8px;border:1px solid #DDD6FE;margin-bottom:8px;">
                <span style="font-weight:600;color:#4A1D96;"><?= htmlspecialchars($cat['nombre_categoria']) ?></span>
                <a href="/DisneyStock/controllers/CategoriaController.php?accion=eliminar&id=<?= $cat['id_categoria'] ?>"
                   onclick="return confirm('\u00bfEliminar categor\u00eda?')"
                   style="color:#DC2626;font-size:0.82rem;text-decoration:none;padding:4px 10px;background:#FEE2E2;border-radius:6px;">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modalTituloP').textContent = 'Nuevo Producto';
    document.getElementById('accionP').value = 'crear';
    document.getElementById('pId').value = '';
    ['pNombre','pProveedor'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('pVenta').value = 0;
    document.getElementById('pCompra').value = 0;
    document.getElementById('pStock').value = 0;
    document.getElementById('pMinimo').value = 0;
    document.getElementById('pFecha').value = '<?= date('Y-m-d') ?>';
    document.getElementById('pCategoria').value = '';
    document.getElementById('modalProducto').style.display = 'flex';
}
function editarProducto(p) {
    document.getElementById('modalTituloP').textContent = 'Editar Producto';
    document.getElementById('accionP').value = 'editar';
    document.getElementById('pId').value        = p.id_producto;
    document.getElementById('pNombre').value    = p.nombre || '';
    document.getElementById('pProveedor').value = p.proveedor || '';
    document.getElementById('pVenta').value     = p.precio_venta || 0;
    document.getElementById('pCompra').value    = p.precio_compra || 0;
    document.getElementById('pStock').value     = p.stock_actual || 0;
    document.getElementById('pMinimo').value    = p.stock_minimo || 0;
    document.getElementById('pFecha').value     = p.fecha_ingreso || '<?= date('Y-m-d') ?>';
    document.getElementById('pCategoria').value = p.id_categoria || '';
    document.getElementById('modalProducto').style.display = 'flex';
}
function abrirModalCat() { document.getElementById('modalCat').style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
function toggleActivo(id, estado) {
    const txt = estado === 'activo' ? 'desactivar' : 'activar';
    Swal.fire({
        title: '\u00bf' + txt.charAt(0).toUpperCase() + txt.slice(1) + ' producto?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4A1D96',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'S\u00ed'
    }).then(r => { if (r.isConfirmed) window.location = '/DisneyStock/controllers/ProductoController.php?accion=toggle&id=' + id; });
}
function eliminarProducto(id, nombre) {
    Swal.fire({
        title: '\u00bfEliminar producto?',
        text: '"' + nombre + '" ser\u00e1 eliminado permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'S\u00ed, eliminar'
    }).then(r => { if (r.isConfirmed) window.location = '/DisneyStock/controllers/ProductoController.php?accion=eliminar&id=' + id; });
}
window.onclick = e => { if (['modalProducto','modalCat'].includes(e.target.id)) cerrarModal(e.target.id); };
</script>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>

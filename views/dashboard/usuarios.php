<?php
// ============================================================
//  DisneyStock — Vista: Usuarios
//  Archivo: views/dashboard/usuarios.php
//
//  Muestra la tabla de usuarios y el modal para crear/editar.
//  No accede a la BD directamente.
// ============================================================
if (!isset($usuarios)) {
    header("Location: /DisneyStock/controllers/UsuarioController.php");
    exit;
}
require_once __DIR__ . '/../../helpers/auth.php';
$rolColors = [
    'admin'    => ['#EDE9FE','#4A1D96'],
    'empleado' => ['#D1FAE5','#065F46'],
];
?>

<!-- Encabezado -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Usuarios del Sistema</h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;"><?= count($usuarios) ?> usuario(s) registrado(s)</p>
    </div>
    <button onclick="abrirModal()" style="background:#4A1D96;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;">
        <i class="fas fa-user-plus"></i> Nuevo Usuario
    </button>
</div>

<!-- Tabla de usuarios -->
<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
        <thead style="background:#F5F3FF;border-bottom:2px solid #DDD6FE;">
            <tr>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Nombre</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Usuario</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Rol</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Estado</th>
                <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600;">Creado</th>
                <th style="padding:12px 16px;text-align:center;color:#6B7280;font-weight:600;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            [$rbg, $rcolor] = $rolColors[$u['rol']] ?? ['#F3F0FF','#334155'];
            $esMiCuenta = $u['id'] === $_SESSION['usuario']['id'];
        ?>
        <tr style="border-bottom:1px solid #F3F0FF;" onmouseover="this.style.background='#F5F3FF'" onmouseout="this.style.background=''">
            <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <!-- Avatar con inicial del nombre -->
                    <div style="width:36px;height:36px;background:#EDE9FE;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#4A1D96;font-size:0.9rem;flex-shrink:0;">
                        <?= strtoupper(substr($u['nombre'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:600;color:#4A1D96;"><?= htmlspecialchars($u['nombre']) ?></div>
                        <?php if ($esMiCuenta): ?>
                        <div style="font-size:0.75rem;color:#7C3AED;font-weight:600;">&larr; Tu cuenta</div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td style="padding:14px 16px;color:#334155;font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($u['usuario']) ?></td>
            <td style="padding:14px 16px;text-align:center;">
                <span style="background:<?= $rbg ?>;color:<?= $rcolor ?>;padding:4px 12px;border-radius:20px;font-size:0.78rem;font-weight:700;text-transform:capitalize;">
                    <?= htmlspecialchars($u['rol']) ?>
                </span>
            </td>
            <td style="padding:14px 16px;text-align:center;">
                <!-- Badge verde si activo, rojo si inactivo -->
                <span style="background:<?= $u['estado']==='activo' ? '#D1FAE5' : '#FEE2E2' ?>;color:<?= $u['estado']==='activo' ? '#065F46' : '#991B1B' ?>;padding:4px 12px;border-radius:20px;font-size:0.78rem;font-weight:600;">
                    <?= $u['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                </span>
            </td>
            <td style="padding:14px 16px;color:#6B7280;font-size:0.82rem;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td style="padding:14px 16px;text-align:center;">
                <!-- Botón editar -->
                <button onclick='editarUsuario(<?= json_encode($u) ?>)' style="background:#EDE9FE;color:#4A1D96;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;margin-right:4px;">
                    <i class="fas fa-edit"></i>
                </button>
                <?php if (!$esMiCuenta): ?>
                <!-- Botón activar/desactivar — no aparece en la propia cuenta -->
                <button onclick="toggleUsuario('<?= $u['id'] ?>','<?= $u['estado'] ?>','<?= htmlspecialchars($u['nombre'],ENT_QUOTES) ?>')"
                        style="background:<?= $u['estado']==='activo' ? '#FEF3C7' : '#D1FAE5' ?>;color:<?= $u['estado']==='activo' ? '#92400E' : '#065F46' ?>;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.82rem;font-weight:600;">
                    <i class="fas fa-<?= $u['estado']==='activo' ? 'ban' : 'check' ?>"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal crear/editar usuario -->
<div id="modalUsuario" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:480px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 id="modalTituloU" style="font-size:1.2rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Nuevo Usuario</h2>
            <button onclick="cerrarModal()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94A3B8;">&times;</button>
        </div>
        <form method="POST" action="/DisneyStock/controllers/UsuarioController.php">
            <input type="hidden" name="accion" id="accionU" value="crear">
            <input type="hidden" name="id" id="uId">
            <?php csrfField(); ?>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Nombre completo *</label>
                    <input type="text" name="nombre" id="uNombre" required maxlength="255" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Nombre de usuario *</label>
                    <input type="text" name="usuario" id="uUsuario" required maxlength="80" minlength="4" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Rol *</label>
                    <select name="rol" id="uRol" style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <option value="empleado">Empleado</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <!-- El label cambia dinámicamente: "Contraseña *" al crear, "Nueva Contraseña" al editar -->
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;" id="lblPassword">Contrase&#241;a *</label>
                    <input type="password" name="password" id="uPassword" minlength="8"
                           style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;"
                           placeholder="M&#237;nimo 8 caracteres">
                    <!-- Solo visible al editar: indica que dejar vacío no cambia la clave -->
                    <p id="hintPassword" style="font-size:0.75rem;color:#94A3B8;margin-top:4px;display:none;">Dejar vac&#237;o para no cambiar la contrase&#241;a.</p>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end;">
                <button type="button" onclick="cerrarModal()" style="padding:10px 20px;border:1.5px solid #DDD6FE;border-radius:8px;background:#fff;color:#6B7280;font-weight:600;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:10px 24px;background:#4A1D96;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Abre el modal en modo "crear" con todos los campos limpios
function abrirModal() {
    document.getElementById('modalTituloU').textContent = 'Nuevo Usuario';
    document.getElementById('accionU').value = 'crear';
    document.getElementById('uId').value     = '';
    document.getElementById('uNombre').value = '';
    document.getElementById('uUsuario').value = '';
    document.getElementById('uRol').value    = 'empleado';
    document.getElementById('uPassword').value   = '';
    document.getElementById('uPassword').required = true;
    document.getElementById('lblPassword').textContent = 'Contrase\u00f1a *';
    document.getElementById('hintPassword').style.display = 'none';
    document.getElementById('modalUsuario').style.display = 'flex';
}

// Abre el modal en modo "editar" con los datos del usuario seleccionado
function editarUsuario(u) {
    document.getElementById('modalTituloU').textContent = 'Editar Usuario';
    document.getElementById('accionU').value  = 'editar';
    document.getElementById('uId').value      = u.id;
    document.getElementById('uNombre').value  = u.nombre;
    document.getElementById('uUsuario').value = u.usuario;
    document.getElementById('uRol').value     = u.rol;
    document.getElementById('uPassword').value   = '';
    document.getElementById('uPassword').required = false; // opcional al editar
    document.getElementById('lblPassword').textContent = 'Nueva Contrase\u00f1a';
    document.getElementById('hintPassword').style.display = 'block';
    document.getElementById('modalUsuario').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

// Confirmación antes de activar o desactivar un usuario
function toggleUsuario(id, estado, nombre) {
    const accion = estado === 'activo' ? 'desactivar' : 'activar';
    Swal.fire({
        title: '\u00bf' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' usuario?',
        text: nombre,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4A1D96',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'S\u00ed'
    }).then(r => {
        if (r.isConfirmed) {
            window.location = '/DisneyStock/controllers/UsuarioController.php?accion=toggle&id=' + id;
        }
    });
}

// Cerrar modal al hacer clic fuera
window.onclick = e => { if (e.target.id === 'modalUsuario') cerrarModal(); };
</script>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>

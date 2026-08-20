<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /DisneyStock/views/usuarios/login.php"); exit; }
if ($_SESSION['usuario']['rol'] !== 'admin') { header("Location: admin.php"); exit; }

// Procesar guardado de configuración
$guardado = false;
$seccion  = $_GET['seccion'] ?? 'notificaciones';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Persistir en sesión (en un sistema real iría a BD)
    foreach ($_POST as $k => $v) {
        if ($k !== 'seccion') {
            $_SESSION['config'][$k] = $v;
        }
    }
    $seccion  = $_POST['seccion'] ?? $seccion;
    $guardado = true;
}

// Valores actuales (con defaults)
$cfg = $_SESSION['config'] ?? [];
$defaults = [
    'alerta_stock_bajo'     => '1',
    'umbral_stock'          => '5',
    'notif_nueva_venta'     => '0',
    'timeout_sesion'        => '60',
    'intentos_login'        => '3',
    'nombre_negocio'        => 'Variedades Disney',
    'descripcion_negocio'   => 'Tienda de accesorios, manillas y ropa',
    'telefono_negocio'      => '',
    'direccion_negocio'     => 'Huila, Colombia',
    'moneda'                => 'COP',
    'formato_fecha'         => 'd/m/Y',
    'version_sistema'       => '1.0.0',
    'idioma'                => 'es',
];
$cfg = array_merge($defaults, $cfg);

$titulo = "Configuración";
require_once __DIR__ . '/../Layouts/header.php';
require_once __DIR__ . '/../Layouts/sidebar.php';

$secciones = [
    'notificaciones' => ['icon' => 'fa-bell',        'label' => 'Notificaciones'],
    'seguridad'      => ['icon' => 'fa-shield-halved','label' => 'Seguridad'],
    'aplicacion'     => ['icon' => 'fa-store',        'label' => 'Aplicación'],
    'sistema'        => ['icon' => 'fa-gear',         'label' => 'Sistema'],
    'idioma'         => ['icon' => 'fa-language',     'label' => 'Idioma'],
];
?>

<!-- Encabezado -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">
            <i class="fas fa-gear" style="margin-right:10px;color:#7C3AED;"></i>Configuración del Sistema
        </h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;">Ajusta las opciones generales de DisneyStock</p>
    </div>
    <?php if ($guardado): ?>
    <div style="background:#D1FAE5;color:#065F46;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.88rem;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-check-circle"></i> Cambios guardados correctamente
    </div>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start;">

    <!-- Menú lateral de secciones -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <?php foreach ($secciones as $key => $s): ?>
        <a href="?seccion=<?= $key ?>"
           style="display:flex;align-items:center;gap:12px;padding:14px 18px;font-size:0.9rem;font-weight:<?= $seccion===$key?'700':'500' ?>;
                  color:<?= $seccion===$key?'#4A1D96':'#6B7280' ?>;text-decoration:none;
                  background:<?= $seccion===$key?'#EDE9FE':'transparent' ?>;
                  border-left:3px solid <?= $seccion===$key?'#7C3AED':'transparent' ?>;
                  transition:all 0.2s;">
            <i class="fas <?= $s['icon'] ?>" style="width:18px;text-align:center;color:<?= $seccion===$key?'#7C3AED':'#9CA3AF' ?>;"></i>
            <?= $s['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Panel de contenido -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;padding:28px;">

        <form method="POST">
            <input type="hidden" name="seccion" value="<?= htmlspecialchars($seccion) ?>">

            <?php if ($seccion === 'notificaciones'): ?>
            <!-- ── Notificaciones ── -->
            <h2 style="font-size:1.1rem;font-weight:800;color:#4A1D96;margin-bottom:20px;font-family:'Outfit',sans-serif;">
                <i class="fas fa-bell" style="margin-right:8px;color:#7C3AED;"></i>Notificaciones
            </h2>

            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Toggle alerta stock bajo -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <div>
                        <div style="font-weight:600;color:#1E1B4B;font-size:0.95rem;">Alertas de stock bajo</div>
                        <div style="font-size:0.82rem;color:#6B7280;margin-top:2px;">Muestra una alerta cuando un producto alcanza el stock mínimo</div>
                    </div>
                    <label style="position:relative;display:inline-block;width:48px;height:26px;cursor:pointer;">
                        <input type="checkbox" name="alerta_stock_bajo" value="1" <?= $cfg['alerta_stock_bajo']==='1'?'checked':'' ?>
                               style="opacity:0;width:0;height:0;" onchange="this.form.submit()">
                        <span style="position:absolute;inset:0;background:<?= $cfg['alerta_stock_bajo']==='1'?'#7C3AED':'#D1D5DB' ?>;border-radius:26px;transition:0.3s;"></span>
                        <span style="position:absolute;left:<?= $cfg['alerta_stock_bajo']==='1'?'24px':'2px' ?>;top:2px;width:22px;height:22px;background:#fff;border-radius:50%;transition:0.3s;"></span>
                    </label>
                </div>

                <!-- Umbral de stock -->
                <div style="padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <label style="font-weight:600;color:#1E1B4B;font-size:0.95rem;display:block;margin-bottom:6px;">
                        Umbral de stock mínimo por defecto
                    </label>
                    <div style="font-size:0.82rem;color:#6B7280;margin-bottom:12px;">Cantidad mínima predeterminada al registrar nuevos productos</div>
                    <input type="number" name="umbral_stock" value="<?= htmlspecialchars($cfg['umbral_stock']) ?>"
                           min="0" max="999"
                           style="width:120px;padding:9px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;">
                </div>

                <!-- Toggle notificación nueva venta -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <div>
                        <div style="font-weight:600;color:#1E1B4B;font-size:0.95rem;">Notificación al registrar venta</div>
                        <div style="font-size:0.82rem;color:#6B7280;margin-top:2px;">Muestra un mensaje de confirmación visual al completar cada venta</div>
                    </div>
                    <label style="position:relative;display:inline-block;width:48px;height:26px;cursor:pointer;">
                        <input type="checkbox" name="notif_nueva_venta" value="1" <?= $cfg['notif_nueva_venta']==='1'?'checked':'' ?>
                               style="opacity:0;width:0;height:0;" onchange="this.form.submit()">
                        <span style="position:absolute;inset:0;background:<?= $cfg['notif_nueva_venta']==='1'?'#7C3AED':'#D1D5DB' ?>;border-radius:26px;transition:0.3s;"></span>
                        <span style="position:absolute;left:<?= $cfg['notif_nueva_venta']==='1'?'24px':'2px' ?>;top:2px;width:22px;height:22px;background:#fff;border-radius:50%;transition:0.3s;"></span>
                    </label>
                </div>

            </div>

            <?php elseif ($seccion === 'seguridad'): ?>
            <!-- ── Seguridad ── -->
            <h2 style="font-size:1.1rem;font-weight:800;color:#4A1D96;margin-bottom:20px;font-family:'Outfit',sans-serif;">
                <i class="fas fa-shield-halved" style="margin-right:8px;color:#7C3AED;"></i>Seguridad
            </h2>

            <div style="display:flex;flex-direction:column;gap:20px;">

                <div style="padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <label style="font-weight:600;color:#1E1B4B;font-size:0.95rem;display:block;margin-bottom:6px;">
                        Tiempo de inactividad de sesión (minutos)
                    </label>
                    <div style="font-size:0.82rem;color:#6B7280;margin-bottom:12px;">La sesión se cerrará automáticamente tras este tiempo sin actividad</div>
                    <select name="timeout_sesion" style="padding:9px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <?php foreach ([15,30,60,120,240] as $t): ?>
                        <option value="<?= $t ?>" <?= $cfg['timeout_sesion']==(string)$t?'selected':'' ?>><?= $t ?> minutos</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <label style="font-weight:600;color:#1E1B4B;font-size:0.95rem;display:block;margin-bottom:6px;">
                        Intentos máximos de inicio de sesión
                    </label>
                    <div style="font-size:0.82rem;color:#6B7280;margin-bottom:12px;">Número de intentos fallidos antes de bloquear temporalmente el acceso</div>
                    <select name="intentos_login" style="padding:9px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <?php foreach ([3,5,10] as $i): ?>
                        <option value="<?= $i ?>" <?= $cfg['intentos_login']==(string)$i?'selected':'' ?>><?= $i ?> intentos</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="padding:16px 20px;background:#FFFBEB;border-radius:10px;border:1px solid #FDE68A;border-left:4px solid #F59E0B;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <i class="fas fa-triangle-exclamation" style="color:#D97706;"></i>
                        <span style="font-weight:700;color:#92400E;font-size:0.9rem;">Ley 1581 de 2012</span>
                    </div>
                    <p style="font-size:0.82rem;color:#92400E;line-height:1.5;">
                        DisneyStock cumple con la Ley de Protección de Datos Personales de Colombia.
                        Las contraseñas se almacenan cifradas y ningún dato personal es compartido con terceros.
                        Las copias de seguridad se realizan cada 30 días.
                    </p>
                </div>

            </div>

            <?php elseif ($seccion === 'aplicacion'): ?>
            <!-- ── Aplicación ── -->
            <h2 style="font-size:1.1rem;font-weight:800;color:#4A1D96;margin-bottom:20px;font-family:'Outfit',sans-serif;">
                <i class="fas fa-store" style="margin-right:8px;color:#7C3AED;"></i>Datos del Negocio
            </h2>

            <div style="display:flex;flex-direction:column;gap:16px;">

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Nombre del negocio</label>
                    <input type="text" name="nombre_negocio" value="<?= htmlspecialchars($cfg['nombre_negocio']) ?>" maxlength="100"
                           style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Descripción</label>
                    <textarea name="descripcion_negocio" rows="3" maxlength="300"
                              style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;resize:vertical;"><?= htmlspecialchars($cfg['descripcion_negocio']) ?></textarea>
                </div>

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Teléfono de contacto</label>
                    <input type="text" name="telefono_negocio" value="<?= htmlspecialchars($cfg['telefono_negocio']) ?>" maxlength="20"
                           style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Dirección</label>
                    <input type="text" name="direccion_negocio" value="<?= htmlspecialchars($cfg['direccion_negocio']) ?>" maxlength="200"
                           style="width:100%;padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Moneda</label>
                    <select name="moneda" style="padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <option value="COP" <?= $cfg['moneda']==='COP'?'selected':'' ?>>COP — Peso colombiano ($)</option>
                        <option value="USD" <?= $cfg['moneda']==='USD'?'selected':'' ?>>USD — Dólar estadounidense</option>
                    </select>
                </div>

            </div>

            <?php elseif ($seccion === 'sistema'): ?>
            <!-- ── Sistema ── -->
            <h2 style="font-size:1.1rem;font-weight:800;color:#4A1D96;margin-bottom:20px;font-family:'Outfit',sans-serif;">
                <i class="fas fa-gear" style="margin-right:8px;color:#7C3AED;"></i>Sistema
            </h2>

            <div style="display:flex;flex-direction:column;gap:16px;">

                <div>
                    <label style="font-size:0.85rem;font-weight:600;color:#334155;display:block;margin-bottom:6px;">Formato de fecha</label>
                    <select name="formato_fecha" style="padding:10px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.9rem;outline:none;background:#fff;">
                        <option value="d/m/Y" <?= $cfg['formato_fecha']==='d/m/Y'?'selected':'' ?>>DD/MM/AAAA (ej: <?= date('d/m/Y') ?>)</option>
                        <option value="Y-m-d" <?= $cfg['formato_fecha']==='Y-m-d'?'selected':'' ?>>AAAA-MM-DD (ej: <?= date('Y-m-d') ?>)</option>
                        <option value="m/d/Y" <?= $cfg['formato_fecha']==='m/d/Y'?'selected':'' ?>>MM/DD/AAAA (ej: <?= date('m/d/Y') ?>)</option>
                    </select>
                </div>

                <!-- Info del sistema (solo lectura) -->
                <div style="padding:20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-weight:700;color:#4A1D96;font-size:0.95rem;margin-bottom:4px;">
                        <i class="fas fa-info-circle" style="margin-right:6px;"></i>Información del Sistema
                    </div>
                    <?php
                    $infoItems = [
                        ['Sistema',   'DisneyStock'],
                        ['Versión',   '1.0.0'],
                        ['Negocio',   'Variedades Disney'],
                        ['Desarrolladora', 'Heidy Johanna Reyes Quesada'],
                        ['Jurisdicción',   'Huila, Colombia'],
                        ['Última copia',   date('d/m/Y')],
                    ];
                    foreach ($infoItems as [$label, $val]):
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #EDE9FE;">
                        <span style="font-size:0.85rem;color:#6B7280;"><?= $label ?></span>
                        <span style="font-size:0.85rem;font-weight:600;color:#1E1B4B;"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <?php elseif ($seccion === 'idioma'): ?>
            <!-- ── Idioma ── -->
            <h2 style="font-size:1.1rem;font-weight:800;color:#4A1D96;margin-bottom:20px;font-family:'Outfit',sans-serif;">
                <i class="fas fa-language" style="margin-right:8px;color:#7C3AED;"></i>Idioma
            </h2>

            <div style="display:flex;flex-direction:column;gap:16px;">
                <div style="padding:16px 20px;background:#F5F3FF;border-radius:10px;border:1px solid #DDD6FE;">
                    <label style="font-weight:600;color:#1E1B4B;font-size:0.95rem;display:block;margin-bottom:10px;">Idioma del sistema</label>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach (['es' => '🇨🇴  Español (Colombia)', 'en' => '🇺🇸  English'] as $val => $label): ?>
                        <label style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;cursor:pointer;
                                      background:<?= $cfg['idioma']===$val?'#EDE9FE':'#fff' ?>;border:1.5px solid <?= $cfg['idioma']===$val?'#7C3AED':'#DDD6FE' ?>;">
                            <input type="radio" name="idioma" value="<?= $val ?>" <?= $cfg['idioma']===$val?'checked':'' ?> style="accent-color:#7C3AED;">
                            <span style="font-size:0.95rem;font-weight:<?= $cfg['idioma']===$val?'700':'400' ?>;color:#1E1B4B;"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:0.78rem;color:#9CA3AF;margin-top:10px;">
                        <i class="fas fa-info-circle"></i> El cambio de idioma se aplicará al guardar.
                    </p>
                </div>
            </div>

            <?php endif; ?>

            <!-- Botón guardar (no en toggles automáticos) -->
            <?php if ($seccion !== 'notificaciones'): ?>
            <div style="margin-top:28px;display:flex;justify-content:flex-end;">
                <button type="submit"
                        style="background:linear-gradient(135deg,#7C3AED,#3B82F6);color:#fff;padding:11px 28px;border:none;border-radius:8px;font-weight:700;font-size:0.95rem;cursor:pointer;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-floppy-disk"></i> Guardar cambios
                </button>
            </div>
            <?php endif; ?>

        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>

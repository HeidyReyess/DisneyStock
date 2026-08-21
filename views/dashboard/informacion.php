<?php
// ============================================================
//  DisneyStock — Vista: Información del Sistema
//  Archivo: views/dashboard/informacion.php
//
//  NO accede a la BD ni verifica sesión directamente.
//  Las variables $cfg, $rol y $nombre las prepara
//  InformacionController.php antes de incluir esta vista.
// ============================================================
if (!isset($cfg)) {
    header("Location: /DisneyStock/controllers/InformacionController.php");
    exit;
}

$rol    = $_SESSION['usuario']['rol'];
$nombre = $_SESSION['usuario']['nombre'];
?>

<!-- Encabezado -->
<div style="margin-bottom:28px;">
    <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">
        <i class="fas fa-circle-info" style="margin-right:10px;color:#7C3AED;"></i>Información del Sistema
    </h1>
    <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;">Datos generales del negocio y del sistema DisneyStock</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- ── Card: Datos del negocio ── -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <!-- Header de la card -->
        <div style="background:linear-gradient(135deg,#4A1D96,#7C3AED);padding:20px 24px;display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-store" style="color:#fff;font-size:1.3rem;"></i>
            </div>
            <div>
                <div style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;">Datos del Negocio</div>
                <div style="font-size:0.8rem;color:rgba(255,255,255,0.7);">Información de Variedades Disney</div>
            </div>
        </div>
        <!-- Contenido -->
        <div style="padding:24px;display:flex;flex-direction:column;gap:0;">
            <?php
            $negocioInfo = [
                ['fa-store',        'Nombre',       $cfg['nombre_negocio']],
                ['fa-align-left',   'Descripción',  $cfg['descripcion_negocio']],
                ['fa-location-dot', 'Ubicación',    $cfg['direccion_negocio']],
                ['fa-phone',        'Teléfono',     $cfg['telefono_negocio'] ?: 'No registrado'],
            ];
            foreach ($negocioInfo as [$icon, $label, $val]):
            ?>
            <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #F3F0FF;">
                <div style="width:36px;height:36px;background:#EDE9FE;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas <?= $icon ?>" style="color:#7C3AED;font-size:0.9rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
                    <div style="font-size:0.95rem;font-weight:600;color:#1E1B4B;margin-top:2px;"><?= htmlspecialchars($val) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Card: Información del sistema ── -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <!-- Header de la card -->
        <div style="background:linear-gradient(135deg,#3B82F6,#7C3AED);padding:20px 24px;display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-gear" style="color:#fff;font-size:1.3rem;"></i>
            </div>
            <div>
                <div style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;">Sistema</div>
                <div style="font-size:0.8rem;color:rgba(255,255,255,0.7);">DisneyStock v1.0.0</div>
            </div>
        </div>
        <!-- Contenido -->
        <div style="padding:24px;display:flex;flex-direction:column;gap:0;">
            <?php
            $sistemaInfo = [
                ['fa-tag',          'Nombre del sistema',   'DisneyStock'],
                ['fa-code-branch',  'Versión',              '1.0.0'],
                ['fa-user-pen',     'Desarrolladora',       'Heidy Johanna Reyes Quesada'],
                ['fa-location-dot', 'Jurisdicción',         'Huila, Colombia'],
                ['fa-scale-balanced','Licencia',            'Privada / Propietaria'],
                ['fa-server',       'Modelo',               'SaaS (Software como Servicio)'],
            ];
            foreach ($sistemaInfo as [$icon, $label, $val]):
            ?>
            <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #F3F0FF;">
                <div style="width:36px;height:36px;background:#DBEAFE;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas <?= $icon ?>" style="color:#3B82F6;font-size:0.9rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
                    <div style="font-size:0.95rem;font-weight:600;color:#1E1B4B;margin-top:2px;"><?= htmlspecialchars($val) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Card: Mi cuenta ── -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#059669,#3B82F6);padding:20px 24px;display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-circle-user" style="color:#fff;font-size:1.3rem;"></i>
            </div>
            <div>
                <div style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;">Mi Cuenta</div>
                <div style="font-size:0.8rem;color:rgba(255,255,255,0.7);">Sesión activa</div>
            </div>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:0;">
            <?php
            $cuentaInfo = [
                ['fa-user',        'Nombre',     $nombre],
                ['fa-id-badge',    'Rol',        ucfirst($rol)],
                ['fa-clock',       'Conexión',   date('d/m/Y H:i')],
            ];
            foreach ($cuentaInfo as [$icon, $label, $val]):
            ?>
            <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #F3F0FF;">
                <div style="width:36px;height:36px;background:#D1FAE5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas <?= $icon ?>" style="color:#059669;font-size:0.9rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
                    <div style="font-size:0.95rem;font-weight:600;color:#1E1B4B;margin-top:2px;"><?= htmlspecialchars($val) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Card: Seguridad y cumplimiento ── -->
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#D97706,#EF4444);padding:20px 24px;display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-shield-halved" style="color:#fff;font-size:1.3rem;"></i>
            </div>
            <div>
                <div style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;color:#fff;">Seguridad y Cumplimiento</div>
                <div style="font-size:0.8rem;color:rgba(255,255,255,0.7);">Protección de datos</div>
            </div>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:12px;">
            <?php
            $seguridadItems = [
                ['fa-lock',           'Contraseñas cifradas con hash seguro'],
                ['fa-user-shield',    'Control de acceso por roles (Admin / Empleado)'],
                ['fa-eye',            'Monitoreo de actividad de usuarios'],
                ['fa-database',       'Copias de seguridad cada 30 días'],
                ['fa-scale-balanced', 'Cumplimiento Ley 1581 de 2012 — Colombia'],
            ];
            foreach ($seguridadItems as [$icon, $texto]):
            ?>
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:30px;height:30px;background:#FEF3C7;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas <?= $icon ?>" style="color:#D97706;font-size:0.82rem;"></i>
                </div>
                <span style="font-size:0.88rem;color:#374151;"><?= $texto ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

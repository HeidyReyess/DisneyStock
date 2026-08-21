<?php
// ============================================================
//  DisneyStock — Vista: Reportes
//  Archivo: views/dashboard/reportes.php
//
//  RESPONSABILIDAD:
//  Muestra la tabla de resultados del reporte seleccionado.
//  NO accede a la BD — solo renderiza variables de ReporteController.
//
//  VARIABLES QUE ESPERA:
//  - $datos    → Array de filas del reporte (ventas, inventario, etc.).
//  - $columnas → Array de nombres de columnas para el encabezado.
//  - $tipo     → Tipo de reporte activo (ventas|inventario|stock_bajo|
//                movimientos).
//  - $desde    → Fecha inicio del filtro.
//  - $hasta    → Fecha fin del filtro.
//
//  SECCIONES DE LA VISTA:
//  1. Encabezado       → Título, contador de registros, botón imprimir.
//  2. Formulario filtros → Select tipo de reporte, fechas desde/hasta,
//                         botón aplicar.
//  3. Tabla dinámica   → Genera columnas automáticamente desde $columnas
//                        y formatea celdas (monedas, fechas, estados,
//                        tipos de movimiento) según el nombre de columna.
//  4. Estilos de impresión → @media print oculta sidebar y formularios.
//
//  ACCESO DIRECTO:
//  Si se accede sin $datos definido, redirige al controller.
// ============================================================
if (!isset($datos)) {
    header("Location: /DisneyStock/controllers/ReporteController.php");
    exit;
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#4A1D96;font-family:'Outfit',sans-serif;">Reportes</h1>
        <p style="color:#6B7280;font-size:0.88rem;margin-top:2px;"><?= count($datos) ?> registro(s)</p>
    </div>
    <button onclick="window.print()" style="background:#059669;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;">
        <i class="fas fa-print"></i> Imprimir
    </button>
</div>

<!-- Selector de reporte y filtros -->
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #DDD6FE;margin-bottom:20px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Tipo de Reporte</label>
        <select name="tipo" onchange="this.form.submit()" style="padding:9px 14px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;background:#fff;">
            <option value="ventas"      <?= $tipo==='ventas'?'selected':'' ?>>Ventas por período</option>
            <option value="inventario"  <?= $tipo==='inventario'?'selected':'' ?>>Inventario valorizado</option>
            <option value="stock_bajo"  <?= $tipo==='stock_bajo'?'selected':'' ?>>Productos con stock bajo</option>
            <option value="movimientos" <?= $tipo==='movimientos'?'selected':'' ?>>Movimientos de inventario</option>
        </select>
    </div>
    <?php if (in_array($tipo, ['ventas','movimientos'])): ?>
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Desde</label>
        <input type="date" name="desde" value="<?= $desde ?>" style="padding:9px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;">
    </div>
    <div>
        <label style="font-size:0.8rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Hasta</label>
        <input type="date" name="hasta" value="<?= $hasta ?>" style="padding:9px 12px;border:1.5px solid #DDD6FE;border-radius:8px;font-size:0.88rem;outline:none;">
    </div>
    <button type="submit" style="background:#7C3AED;color:#fff;padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:0.88rem;">Aplicar</button>
    <?php endif; ?>
</form>

<!-- Tabla de resultados -->
<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(74,29,150,0.07);border:1px solid #DDD6FE;overflow:hidden;" id="tablaReporte">
    <?php if (empty($datos)): ?>
    <div style="text-align:center;padding:60px 20px;color:#94A3B8;">
        <i class="fas fa-chart-bar" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
        <p>No hay datos para mostrar.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
        <thead style="background:#4A1D96;color:#fff;">
            <tr>
                <?php foreach ($columnas as $col): ?>
                <th style="padding:12px 14px;text-align:left;font-weight:600;white-space:nowrap;"><?= $col ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($datos as $fila): $vals = array_values($fila); ?>
        <tr style="border-bottom:1px solid #F3F0FF;" onmouseover="this.style.background='#F5F3FF'" onmouseout="this.style.background=''">
            <?php foreach ($vals as $i => $v): ?>
            <td style="padding:10px 14px;color:#334155;white-space:nowrap;">
                <?php
                // Formatear monedas
                if (in_array($columnas[$i] ?? '', ['Subtotal','Descuento','Total','Valor Costo','Valor Venta'])) {
                    echo '$' . number_format((float)$v, 0, ',', '.');
                } elseif (in_array($columnas[$i] ?? '', ['Fecha','Actualización'])) {
                    echo $v ? date('d/m/Y', strtotime($v)) : '—';
                } elseif ($columnas[$i] === 'Estado') {
                    $ec = match($v) { 'completada'=>['#D1FAE5','#065F46'], 'anulada'=>['#FEE2E2','#991B1B'], default=>['#FEF3C7','#92400E'] };
                    echo "<span style='background:{$ec[0]};color:{$ec[1]};padding:2px 8px;border-radius:20px;font-size:0.78rem;font-weight:600;text-transform:capitalize;'>$v</span>";
                } elseif ($columnas[$i] === 'Tipo') {
                    $tc = $v === 'entrada' ? ['#D1FAE5','#065F46'] : ($v === 'salida' ? ['#FEE2E2','#991B1B'] : ['#EDE9FE','#4A1D96']);
                    echo "<span style='background:{$tc[0]};color:{$tc[1]};padding:2px 8px;border-radius:20px;font-size:0.78rem;font-weight:600;text-transform:capitalize;'>$v</span>";
                } else {
                    echo htmlspecialchars($v ?? '—');
                }
                ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    aside, .no-print, form { display: none !important; }
    body { background: white !important; }
    #tablaReporte { box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

<?php require_once __DIR__ . '/../Layouts/footer.php'; ?>


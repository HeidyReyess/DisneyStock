<?php
// ============================================================
//  DisneyStock — Partial: Detalle de Venta (respuesta AJAX)
//  Archivo: views/partials/detalle_venta.php
//
//  Este archivo NO se abre directamente. Lo carga VentaController
//  cuando recibe ?accion=detalle, y el HTML generado se inyecta
//  en el div #contenidoDetalle del modal de ventas.php via fetch().
// ============================================================

// Definir colores del badge de estado según el valor de la venta
// completada → fondo verde | anulada → fondo rojo | otros → amarillo
$ec = match($venta['estado']) {
    'completada' => ['#D1FAE5', '#065F46'],
    'anulada'    => ['#FEE2E2', '#991B1B'],
    default      => ['#FEF3C7', '#92400E'],
};
?>

<!-- ── Sección 1: Cabecera de la venta ───────────────────── -->
<!-- Muestra factura, vendedor, fecha y badge de estado -->
<div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #DDD6FE;">

    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="color:#6B7280;font-size:0.85rem;">Factura</span>
        <strong style="color:#4A1D96;"><?= htmlspecialchars($venta['numero_factura'] ?? '—') ?></strong>
    </div>

    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="color:#6B7280;font-size:0.85rem;">Vendedor</span>
        <span><?= htmlspecialchars($venta['vendedor'] ?? '—') ?></span>
    </div>

    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="color:#6B7280;font-size:0.85rem;">Fecha</span>
        <!-- Formatear timestamp de BD a formato legible dd/mm/YYYY HH:mm -->
        <span><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></span>
    </div>

    <div style="display:flex;justify-content:space-between;">
        <span style="color:#6B7280;font-size:0.85rem;">Estado</span>
        <!-- Badge con colores dinámicos según $ec definido arriba -->
        <span style="background:<?= $ec[0] ?>;color:<?= $ec[1] ?>;padding:2px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;text-transform:capitalize;">
            <?= htmlspecialchars($venta['estado']) ?>
        </span>
    </div>

</div>

<!-- ── Sección 2: Tabla de ítems de la venta ─────────────── -->
<!-- Itera $detalles (cada producto comprado en esta venta) -->
<table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-bottom:16px;">
    <thead>
        <tr style="background:#F5F3FF;">
            <th style="padding:8px 10px;text-align:left;color:#6B7280;">Producto</th>
            <th style="padding:8px 10px;text-align:center;color:#6B7280;">Cant.</th>
            <th style="padding:8px 10px;text-align:right;color:#6B7280;">Precio</th>
            <th style="padding:8px 10px;text-align:right;color:#6B7280;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($detalles as $d): ?>
    <tr style="border-bottom:1px solid #F3F0FF;">
        <td style="padding:8px 10px;font-weight:600;color:#4A1D96;"><?= htmlspecialchars($d['nombre']) ?></td>
        <td style="padding:8px 10px;text-align:center;"><?= $d['cantidad'] ?></td>
        <!-- Precio unitario formateado en pesos colombianos -->
        <td style="padding:8px 10px;text-align:right;">$<?= number_format($d['precio_unitario'], 0, ',', '.') ?></td>
        <!-- Subtotal = precio_unitario × cantidad, ya calculado en BD -->
        <td style="padding:8px 10px;text-align:right;font-weight:700;">$<?= number_format($d['subtotal'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- ── Sección 3: Resumen de totales ─────────────────────── -->
<!-- Muestra subtotal, descuento aplicado y total final -->
<div style="background:#F5F3FF;border-radius:8px;padding:12px 16px;">

    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem;">
        <span style="color:#6B7280;">Subtotal</span>
        <span>$<?= number_format($venta['subtotal'], 0, ',', '.') ?></span>
    </div>

    <!-- Descuento en rojo para indicar que es una resta -->
    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:0.88rem;">
        <span style="color:#6B7280;">Descuento</span>
        <span style="color:#DC2626;">-$<?= number_format($venta['descuento'], 0, ',', '.') ?></span>
    </div>

    <!-- Total final: subtotal - descuento -->
    <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:800;color:#4A1D96;">
        <span>TOTAL</span>
        <span>$<?= number_format($venta['total'], 0, ',', '.') ?></span>
    </div>

</div>

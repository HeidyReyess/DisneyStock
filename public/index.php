<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DisneyStock — Sistema de Gestión para Variedades Disney</title>
    <meta name="description" content="Sistema web de gestión de inventario y ventas para Variedades Disney. Automatiza tu stock, registra ventas y toma decisiones con datos reales.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- CSS propio del proyecto -->
    <link rel="stylesheet" href="style.css?v=4.0">

    <!-- Font Awesome (íconos) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <!-- ============================================================
         NAVBAR
         ============================================================ -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="navbar-inner">
                <a href="#inicio" class="logo" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                    <img src="../img/LogoSolo.png" alt="DisneyStock" style="height:52px;width:auto;object-fit:contain;">
                    <span style="font-family:'Outfit',sans-serif;font-size:1.6rem;font-weight:800;color:#4A1D96;letter-spacing:-0.5px;">DisneyStock</span>
                </a>

                <ul class="nav-links">
                    <li><a href="#inicio"    class="nav-link">Inicio</a></li>
                    <li><a href="#modulos"   class="nav-link">Módulos</a></li>
                    <li><a href="#nosotros"  class="nav-link">Nosotros</a></li>
                    <li><a href="#objetivos" class="nav-link">Objetivos</a></li>
                    <li><a href="../views/usuarios/login.php" class="nav-btn-ingresar">Ingresar</a></li>
                </ul>

                <div class="mobile-menu-btn" style="display:none;">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </nav>


    <!-- ============================================================
         HERO — CARRUSEL
         ============================================================ -->
    <section id="inicio" style="padding: 0;">

        <!-- ── Slide 1: Local Variedades Disney ── -->
        <div class="slide active">
            <img src="../img/Imagen1.jpeg"
                 class="slide-bg" alt="Variedades Disney">
            <div class="slide-content">
                <h1>Bienvenido a Variedades Disney</h1>
                <p>DisneyStock reemplaza los cuadernos manuales y centraliza todo tu inventario y ventas en un solo lugar, desde cualquier navegador.</p>
                <div class="hero-btns">
                    <a href="../views/usuarios/login.php" class="btn btn-primary">Ingresar al Sistema</a>
                    <a href="#modulos" class="btn btn-accent">Ver Módulos</a>
                </div>
            </div>
        </div>

        <!-- ── Slide 2: Accesorios / manillas ── -->
        <div class="slide">
            <img src="https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=1600&q=80"
                 class="slide-bg" alt="Accesorios y Manillas">
            <div class="slide-content">
                <h1>Controla cada Accesorio</h1>
                <p>Manillas, collares, aretes y más — registra cada producto con su precio, cantidad y proveedor. Recibe alertas antes de quedarte sin stock.</p>
                <div class="hero-btns">
                    <a href="../views/usuarios/login.php" class="btn btn-primary">Ingresar al Sistema</a>
                    <a href="#modulos" class="btn btn-accent">Ver Módulos</a>
                </div>
            </div>
        </div>

        <!-- ── Slide 3: Ropa / vestidos ── -->
        <div class="slide">
            <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=1600&q=80"
                 class="slide-bg" alt="Ropa y Vestidos">
            <div class="slide-content">
                <h1>Ropa y Moda sin Desorden</h1>
                <p>Lleva el inventario de vestidos y prendas de forma ordenada. Consulta cualquier producto en segundos y olvídate de los cuadernos.</p>
                <div class="hero-btns">
                    <a href="../views/usuarios/login.php" class="btn btn-primary">Ingresar al Sistema</a>
                    <a href="#modulos" class="btn btn-accent">Ver Módulos</a>
                </div>
            </div>
        </div>

        <!-- ── Slide 4: Ventas / caja ── -->
        <div class="slide">
            <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1600&q=80"
                 class="slide-bg" alt="Registro de Ventas">
            <div class="slide-content">
                <h1>Ventas Registradas al Instante</h1>
                <p>Cada venta descuenta el stock automáticamente. Consulta tus ventas del día, la semana o el mes con un solo clic.</p>
                <div class="hero-btns">
                    <a href="../views/usuarios/login.php" class="btn btn-primary">Ingresar al Sistema</a>
                    <a href="#modulos" class="btn btn-accent">Ver Módulos</a>
                </div>
            </div>
        </div>

        <!-- Botones de navegación -->
        <button class="btn-slide btn-slide-prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="btn-slide btn-slide-next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Puntos indicadores -->
        <div class="slide-dots" id="slideDots"></div>
    </section>


    <!-- ============================================================
         SECCIÓN MÓDULOS
         ============================================================ -->
    <section id="modulos">
        <div class="container">
            <div class="section-header">
                <span>Funcionalidades</span>
                <h2>Todo lo que Variedades Disney Necesita</h2>
                <p>DisneyStock digitaliza los procesos clave de tu negocio — inventario, ventas, clientes y reportes — en una sola plataforma web.</p>
            </div>

            <div class="roles-grid">

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <h3>Inventario</h3>
                    <p>Registra y consulta productos con nombre, categoría, cantidad, precio, fecha de ingreso y proveedor. Alertas automáticas de stock bajo.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-cash-register"></i></div>
                    <h3>Ventas</h3>
                    <p>Registra ventas diarias y el sistema descuenta el stock automáticamente. Consulta el historial de ventas por fecha o producto.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-users"></i></div>
                    <h3>Clientes</h3>
                    <p>Centraliza la información de tus clientes y consulta su historial de compras. Identifica a tus mejores compradores con facilidad.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-chart-bar"></i></div>
                    <h3>Reportes</h3>
                    <p>Reportes de ventas por período, inventario valorizado y productos con bajo stock. Información clara para tomar mejores decisiones.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-user-shield"></i></div>
                    <h3>Control de Roles</h3>
                    <p>Dos niveles de acceso: <strong>Administrador</strong> con control total y <strong>Empleado</strong> con permisos limitados. Cada usuario ve solo lo que necesita.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon"><i class="fas fa-bell"></i></div>
                    <h3>Alertas de Stock</h3>
                    <p>El sistema te avisa automáticamente cuando un producto llega al mínimo definido, para que hagas el pedido antes de quedarte sin él.</p>
                </div>

            </div>
        </div>
    </section>


    <!-- ============================================================
         NOSOTROS — MISIÓN Y VISIÓN
         ============================================================ -->
    <section id="nosotros" style="background: var(--primary-ultra);">
        <div class="container">
            <div class="section-header">
                <span>Sobre el Proyecto</span>
                <h2>DisneyStock para Variedades Disney</h2>
                <p>Un sistema desarrollado a la medida del negocio, con metodología en cascada y enfoque en seguridad de datos conforme a la Ley 1581 de 2012.</p>
            </div>

            <div class="roles-grid">
                <div class="role-card" style="border-top: 4px solid var(--primary-light);">
                    <div class="role-icon"><i class="fas fa-bullseye"></i></div>
                    <h3>Misión</h3>
                    <p>
                        Automatizar el inventario y el registro de ventas de Variedades Disney, reemplazando
                        los cuadernos manuales por un sistema digital que centraliza toda la información,
                        reduce errores y permite consultar datos en segundos.
                    </p>
                </div>

                <div class="role-card" style="border-top: 4px solid var(--accent-dark);">
                    <div class="role-icon"><i class="fas fa-eye"></i></div>
                    <h3>Visión</h3>
                    <p>
                        Ser la herramienta de referencia para la gestión de Variedades Disney, escalable
                        hacia otros locales del Huila, reconocida por su facilidad de uso, seguridad
                        y confiabilidad en el control del negocio.
                    </p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Cuadernos Manuales</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Web & Seguro</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">2</span>
                    <span class="stat-label">Roles de Acceso</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">30d</span>
                    <span class="stat-label">Copias de Seguridad</span>
                </div>
            </div>
        </div>
    </section>


    <!-- ============================================================
         OBJETIVOS
         ============================================================ -->
    <section id="objetivos">
        <div class="container">
            <div class="section-header">
                <span>Objetivos del Sistema</span>
                <h2>¿Por qué DisneyStock?</h2>
                <p>Diseñado bajo metodología en cascada, con seguridad robusta y pensado para el día a día de Variedades Disney en el Huila, Colombia.</p>
            </div>

            <div class="roles-grid">
                <div class="role-card">
                    <div class="role-icon" style="color: var(--sky);"><i class="fas fa-bolt"></i></div>
                    <h3>Automatización</h3>
                    <p>Cada venta actualiza el inventario sola. Los cálculos de totales, descuentos y alertas de stock se hacen automáticamente, sin intervención manual.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon" style="color: var(--sky);"><i class="fas fa-shield-halved"></i></div>
                    <h3>Seguridad</h3>
                    <p>Contraseñas cifradas, control de roles, monitoreo de actividad y cumplimiento de la <strong>Ley 1581 de 2012</strong> de protección de datos de Colombia.</p>
                </div>

                <div class="role-card">
                    <div class="role-icon" style="color: var(--sky);"><i class="fas fa-chart-line"></i></div>
                    <h3>Trazabilidad</h3>
                    <p>Cada movimiento de inventario y cada venta queda registrado con fecha y usuario, permitiendo auditorías completas y decisiones basadas en datos reales.</p>
                </div>
            </div>
        </div>
    </section>


    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                        <img src="../img/LogoSolo.png" alt="DisneyStock" style="height:52px;width:auto;object-fit:contain;filter:brightness(0) invert(1);">
                        <span style="font-family:'Outfit',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-0.5px;">DisneyStock</span>
                    </a>
                    <p>Sistema de gestión de inventario y ventas para Variedades Disney. Datos seguros, decisiones inteligentes.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <div class="footer-col">
                    <h4 class="footer-title">Navegación</h4>
                    <ul class="footer-links">
                        <li><a href="#inicio">Inicio</a></li>
                        <li><a href="#modulos">Módulos</a></li>
                        <li><a href="#nosotros">Nosotros</a></li>
                        <li><a href="#objetivos">Objetivos</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4 class="footer-title">Legal</h4>
                    <ul class="footer-links">
                        <li><a href="#">Privacidad de Datos</a></li>
                        <li><a href="#">Ley 1581 de 2012</a></li>
                        <li><a href="#">Términos de Uso</a></li>
                        <li><a href="#">Soporte</a></li>
                    </ul>
                </div>

                <div class="footer-col footer-contact">
                    <h4 class="footer-title">Desarrolladora</h4>
                    <p><i class="fas fa-user"></i> Heidy Johanna Reyes Quesada</p>
                    <p><i class="fas fa-location-dot"></i> Huila, Colombia</p>
                    <p><i class="fas fa-store"></i> Variedades Disney</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2026 DisneyStock · Variedades Disney. Todos los derechos reservados.</p>
                <p>Desarrollado con <i class="fas fa-heart" style="color: var(--accent);"></i> en el Huila, Colombia.</p>
            </div>
        </div>
    </footer>


    <!-- ============================================================
         JAVASCRIPT — Carrusel y Navbar
         ============================================================ -->
    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dotsContainer = document.getElementById('slideDots');

        slides.forEach((_, i) => {
            const dot = document.createElement('button');
            dot.classList.add('dot');
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(i));
            dotsContainer.appendChild(dot);
        });

        function getDots() { return document.querySelectorAll('.dot'); }

        function goToSlide(index) {
            slides[currentSlide].classList.remove('active');
            getDots()[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            getDots()[currentSlide].classList.add('active');
        }

        function changeSlide(direction) { goToSlide(currentSlide + direction); }

        setInterval(() => changeSlide(1), 6000);

        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });
    </script>

</body>
</html>

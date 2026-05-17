<?php $p = basename($_SERVER['PHP_SELF']); $es_admin = in_array('Admin', $_SESSION['roles'] ?? []); ?>
<aside class="sidebar">
    <div class="sidebar-logo">NIDD0</div>
    <nav class="sidebar-nav">
        <a href="dashboard.php"    class="<?= $p==='dashboard.php'    ?'activo':'' ?>">Inicio</a>
        <a href="dispositivos.php" class="<?= $p==='dispositivos.php' ?'activo':'' ?>">Dispositivos</a>
        <?php if ($es_admin): ?>
        <a href="usuarios.php"     class="<?= $p==='usuarios.php'     ?'activo':'' ?>">Usuarios</a>
        <a href="eventos.php"      class="<?= $p==='eventos.php'      ?'activo':'' ?>">Eventos</a>
        <a href="discos.php"       class="<?= $p==='discos.php'       ?'activo':'' ?>">Discos</a>
        <?php endif; ?>
        <a href="restaurar.php"    class="<?= $p==='restaurar.php'    ?'activo':'' ?>">Restaurar</a>
    </nav>
    <div class="sidebar-footer">
        <?= htmlspecialchars($_SESSION['nombre']) ?> &mdash;
        <a href="logout.php" class="sidebar-logout">salir</a>
    </div>
</aside>

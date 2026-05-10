<?php $p = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">N</div>
        <div class="sidebar-logo-text">NID<span>DO</span></div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php"    class="<?= $p==='dashboard.php'    ?'activo':'' ?>"><span class="nav-icon">⬚</span> Dashboard</a>
        <a href="dispositivos.php" class="<?= $p==='dispositivos.php' ?'activo':'' ?>"><span class="nav-icon">◫</span> Dispositivos</a>
        <a href="usuarios.php"     class="<?= $p==='usuarios.php'     ?'activo':'' ?>"><span class="nav-icon">◯</span> Usuarios</a>
        <a href="eventos.php"      class="<?= $p==='eventos.php'      ?'activo':'' ?>"><span class="nav-icon">◈</span> Eventos</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['nombre'],0,1)) ?></div>
            <div class="sidebar-username"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
        </div>
        <a href="logout.php" class="sidebar-logout">salir</a>
    </div>
</aside>

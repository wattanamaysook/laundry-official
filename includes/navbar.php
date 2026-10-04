<!-- Navbar -->
<nav class="navbar">

    <div class="logo">
        LAUNDRY
    </div>

    <div class="nav-links">

        <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>

            <!-- หน้าแรก -->
            <a href="index.php">
                หน้าแรก
            </a>

            <!-- สาขา -->
            <a href="pages/branches.php">
                สาขา
            </a>

            <!-- History -->
            <a href="pages/history.php">
                ประวัติ
            </a>

        <?php else: ?>

            <!-- หน้าแรก -->
            <a href="../index.php">
                หน้าแรก
            </a>

            <!-- สาขา -->
            <a href="branches.php">
                สาขา
            </a>

            <!-- History -->
            <a href="history.php">
                ประวัติ
            </a>

        <?php endif; ?>


        <!-- Services -->
        <a href="#">
            บริการ
        </a>


        <!-- Contact -->
        <a href="#">
            ติดต่อเรา
        </a>

    </div>

</nav>
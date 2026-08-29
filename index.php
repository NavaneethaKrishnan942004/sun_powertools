<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content">

    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Breadcrumb Navigation -->
        <?php
        renderBreadcrumbs([
            ['title' => '', 'icon' => 'bi bi-house-door', 'link' => 'index.php'],
            ['title' => 'Dashboard']
        ]);
        ?>

        <!-- Page Heading -->
        <div class="page-heading">

            <div class="page-heading-copy">

                <span class="page-icon">
                    <i class="bi bi-speedometer2"></i>
                </span>

                

                    <p class="eyebrow mb-1">
                        Overview
                    </p>

                    <h1 class="h3 mb-1">
                        Dashboard
                    </h1>
<div>
                    <p class="text-muted mb-0">
                        Monitor performance, sales, users, and support from one clean workspace.
                    </p>

                </div>

            </div>

        </div>


        <!-- Dashboard Cards -->

        <section class="row g-3 mt-1">

            <!-- Revenue -->
            <div class="col-12 col-sm-6 col-xl-3">

                <article class="metric-card metric-primary">

                    <div class="metric-top">

                        <span class="metric-label">
                            Revenue
                        </span>

                        <span class="metric-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </span>

                    </div>

                    <div class="metric-value">
                        $48,240
                    </div>

                    <div class="metric-meta">

                        <span class="text-success">
                            +12.5%
                        </span>

                        <span>
                            from last month
                        </span>

                    </div>

                </article>

            </div>


            <!-- Orders -->
            <div class="col-12 col-sm-6 col-xl-3">

                <article class="metric-card metric-success">

                    <div class="metric-top">

                        <span class="metric-label">
                            Orders
                        </span>

                        <span class="metric-icon">
                            <i class="bi bi-bag-check"></i>
                        </span>

                    </div>

                    <div class="metric-value">
                        1,284
                    </div>

                    <div class="metric-meta">

                        <span class="text-success">
                            +8.2%
                        </span>

                        <span>
                            new orders
                        </span>

                    </div>

                </article>

            </div>


            <!-- Customers -->
            <div class="col-12 col-sm-6 col-xl-3">

                <article class="metric-card metric-warning">

                    <div class="metric-top">

                        <span class="metric-label">
                            Customers
                        </span>

                        <span class="metric-icon">
                            <i class="bi bi-people"></i>
                        </span>

                    </div>

                    <div class="metric-value">
                        8,742
                    </div>

                    <div class="metric-meta">

                        <span class="text-success">
                            +5.1%
                        </span>

                        <span>
                            active users
                        </span>

                    </div>

                </article>

            </div>


            <!-- Tickets -->
            <div class="col-12 col-sm-6 col-xl-3">

                <article class="metric-card metric-danger">

                    <div class="metric-top">

                        <span class="metric-label">
                            Tickets
                        </span>

                        <span class="metric-icon">
                            <i class="bi bi-life-preserver"></i>
                        </span>

                    </div>

                    <div class="metric-value">
                        36
                    </div>

                    <div class="metric-meta">

                        <span class="text-danger">
                            3 urgent
                        </span>

                        <span>
                            need review
                        </span>

                    </div>

                </article>

            </div>

        </section>


        <!-- Sales + Activity -->

        <section class="row g-3 mt-1">

            <div class="col-12 col-xl-8">

                <div class="panel">

                    <div class="panel-header">

                        <div>

                            <h2 class="h5 mb-1 section-title">

                                <i class="bi bi-graph-up-arrow"></i>

                                <span>
                                    Sales Performance
                                </span>

                            </h2>

                            <p class="text-muted mb-0">
                                Monthly revenue performance.
                            </p>

                        </div>

                        <a
                            href="charts.php"
                            class="btn btn-light btn-sm"
                        >
                            View Details
                        </a>

                    </div>


                    <div class="chart-bars">

                        <div class="chart-column bar-42">
                            <span></span>
                            <small>Jan</small>
                        </div>

                        <div class="chart-column bar-58">
                            <span></span>
                            <small>Feb</small>
                        </div>

                        <div class="chart-column bar-51">
                            <span></span>
                            <small>Mar</small>
                        </div>

                        <div class="chart-column bar-72">
                            <span></span>
                            <small>Apr</small>
                        </div>

                        <div class="chart-column bar-66">
                            <span></span>
                            <small>May</small>
                        </div>

                        <div class="chart-column bar-83">
                            <span></span>
                            <small>Jun</small>
                        </div>

                    </div>

                </div>

            </div>

        </section>


    </div>

</main>


<?php

require_once __DIR__ . '/includes/footer.php';

?>
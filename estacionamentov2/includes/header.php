<?php

if (!isset($tituloPagina)) {
    $tituloPagina = "Estacionamento";
}

$baseUrl = "/estacionamentov2";

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($tituloPagina) ?> - Estacionamento
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- CSS do sistema -->
    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>/assets/css/style.css"
    >

</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-estacionamento">

    <div class="container-fluid px-4">

        <!-- LOGO -->

        <a
            class="navbar-brand fw-bold"
            href="<?= $baseUrl ?>/index.php"
        >

            <i class="bi bi-p-square-fill"></i>

            ParkSystem

        </a>


        <!-- BOTÃO MOBILE -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menuPrincipal"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <!-- MENU -->

        <div
            class="collapse navbar-collapse"
            id="menuPrincipal"
        >

            <ul class="navbar-nav ms-auto">


                <!-- DASHBOARD -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/index.php"
                    >

                        <i class="bi bi-speedometer2"></i>

                        Dashboard

                    </a>

                </li>


                <!-- CLIENTES -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/clientes.php"
                    >

                        <i class="bi bi-people"></i>

                        Clientes

                    </a>

                </li>


                <!-- VEÍCULOS -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/veiculos.php"
                    >

                        <i class="bi bi-car-front"></i>

                        Veículos

                    </a>

                </li>


                <!-- VAGAS -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/vagas.php"
                    >

                        <i class="bi bi-grid-3x3-gap"></i>

                        Vagas

                    </a>

                </li>


                <!-- ENTRADA -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/entrada.php"
                    >

                        <i class="bi bi-box-arrow-in-right"></i>

                        Entrada

                    </a>

                </li>


                <!-- SAÍDA -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/saida.php"
                    >

                        <i class="bi bi-box-arrow-right"></i>

                        Saída

                    </a>

                </li>


                <!-- PREÇOS -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/precos.php"
                    >

                        <i class="bi bi-cash-coin"></i>

                        Preços

                    </a>

                </li>


                <!-- RELATÓRIOS -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= $baseUrl ?>/pages/relatorios.php"
                    >

                        <i class="bi bi-bar-chart"></i>

                        Relatórios

                    </a>

                </li>


            </ul>

        </div>

    </div>

</nav>


<!-- CONTEÚDO DA PÁGINA -->

<main class="container-fluid px-4 py-4">
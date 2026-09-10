<?php

require "config/banco.php";

$tituloPagina = "Dashboard";


/*
|--------------------------------------------------------------------------
| ESTATÍSTICAS
|--------------------------------------------------------------------------
*/

// Total de vagas
$totalVagas = $db
    ->query("SELECT COUNT(*) FROM vagas")
    ->fetchColumn();


// Vagas livres
$vagasLivres = $db
    ->query("
        SELECT COUNT(*)
        FROM vagas
        WHERE status = 'Livre'
    ")
    ->fetchColumn();


// Vagas ocupadas
$vagasOcupadas = $db
    ->query("
        SELECT COUNT(*)
        FROM vagas
        WHERE status = 'Ocupada'
    ")
    ->fetchColumn();


// Total de clientes
$totalClientes = $db
    ->query("SELECT COUNT(*) FROM clientes")
    ->fetchColumn();


// Total de veículos
$totalVeiculos = $db
    ->query("SELECT COUNT(*) FROM veiculos")
    ->fetchColumn();


// Veículos atualmente estacionados
$veiculosEstacionados = $db
    ->query("
        SELECT COUNT(*)
        FROM estacionamentos
        WHERE saida IS NULL
    ")
    ->fetchColumn();


// Faturamento total
$faturamentoTotal = $db
    ->query("
        SELECT COALESCE(SUM(valor), 0)
        FROM estacionamentos
        WHERE saida IS NOT NULL
    ")
    ->fetchColumn();


// Entradas realizadas hoje
$entradasHoje = $db
    ->query("
        SELECT COUNT(*)
        FROM estacionamentos
        WHERE date(entrada) = date('now', 'localtime')
    ")
    ->fetchColumn();


// Saídas realizadas hoje
$saidasHoje = $db
    ->query("
        SELECT COUNT(*)
        FROM estacionamentos
        WHERE saida IS NOT NULL
        AND date(saida) = date('now', 'localtime')
    ")
    ->fetchColumn();


// Faturamento de hoje
$faturamentoHoje = $db
    ->query("
        SELECT COALESCE(SUM(valor), 0)
        FROM estacionamentos
        WHERE saida IS NOT NULL
        AND date(saida) = date('now', 'localtime')
    ")
    ->fetchColumn();


require "includes/header.php";

?>


<!-- TÍTULO -->

<div class="mb-4">

    <h1 class="page-title">
        Dashboard
    </h1>

    <p class="page-subtitle">
        Visão geral do estacionamento
    </p>

</div>


<!-- CARDS PRINCIPAIS -->

<div class="row g-4 mb-4">


    <!-- VAGAS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-grid-3x3-gap"></i>

            </div>

            <h2>
                <?= $totalVagas ?>
            </h2>

            <p>
                Total de vagas
            </p>

        </div>

    </div>


    <!-- VAGAS LIVRES -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-check-circle"></i>

            </div>

            <h2>
                <?= $vagasLivres ?>
            </h2>

            <p>
                Vagas livres
            </p>

        </div>

    </div>


    <!-- VAGAS OCUPADAS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-car-front"></i>

            </div>

            <h2>
                <?= $vagasOcupadas ?>
            </h2>

            <p>
                Vagas ocupadas
            </p>

        </div>

    </div>


    <!-- CLIENTES -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-people"></i>

            </div>

            <h2>
                <?= $totalClientes ?>
            </h2>

            <p>
                Clientes cadastrados
            </p>

        </div>

    </div>

</div>


<!-- SEGUNDA LINHA -->

<div class="row g-4 mb-4">


    <!-- VEÍCULOS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-car-front-fill"></i>

            </div>

            <h2>
                <?= $totalVeiculos ?>
            </h2>

            <p>
                Veículos cadastrados
            </p>

        </div>

    </div>


    <!-- ESTACIONADOS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-p-square"></i>

            </div>

            <h2>
                <?= $veiculosEstacionados ?>
            </h2>

            <p>
                Veículos estacionados
            </p>

        </div>

    </div>


    <!-- ENTRADAS HOJE -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-box-arrow-in-right"></i>

            </div>

            <h2>
                <?= $entradasHoje ?>
            </h2>

            <p>
                Entradas hoje
            </p>

        </div>

    </div>


    <!-- SAÍDAS HOJE -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-box-arrow-right"></i>

            </div>

            <h2>
                <?= $saidasHoje ?>
            </h2>

            <p>
                Saídas hoje
            </p>

        </div>

    </div>

</div>


<!-- INFORMAÇÕES FINANCEIRAS -->

<div class="row g-4 mb-4">


    <!-- FATURAMENTO TOTAL -->

    <div class="col-md-6">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-cash-stack"></i>

            </div>

            <h2>

                R$

                <?= number_format(
                    $faturamentoTotal,
                    2,
                    ",",
                    "."
                ) ?>

            </h2>

            <p>
                Faturamento total
            </p>

        </div>

    </div>


    <!-- FATURAMENTO HOJE -->

    <div class="col-md-6">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-currency-dollar"></i>

            </div>

            <h2>

                R$

                <?= number_format(
                    $faturamentoHoje,
                    2,
                    ",",
                    "."
                ) ?>

            </h2>

            <p>
                Faturamento de hoje
            </p>

        </div>

    </div>

</div>


<!-- STATUS DAS VAGAS -->

<div class="table-container">

    <h4 class="mb-4">

        <i class="bi bi-bar-chart"></i>

        Ocupação do estacionamento

    </h4>


    <?php

    $percentualOcupado = 0;

    if ($totalVagas > 0) {

        $percentualOcupado =
            ($vagasOcupadas / $totalVagas) * 100;
    }

    ?>


    <div class="mb-3">

        <div class="d-flex justify-content-between">

            <span>
                Ocupação
            </span>

            <strong>

                <?= number_format(
                    $percentualOcupado,
                    0
                ) ?>%

            </strong>

        </div>

    </div>


    <div
        class="progress"
        style="height: 25px;"
    >

        <div
            class="progress-bar"
            role="progressbar"
            style="width: <?= $percentualOcupado ?>%;"
            aria-valuenow="<?= $percentualOcupado ?>"
            aria-valuemin="0"
            aria-valuemax="100"
        >

            <?= $vagasOcupadas ?> /
            <?= $totalVagas ?>

        </div>

    </div>


    <div class="row mt-4 text-center">


        <div class="col-md-4">

            <strong>
                <?= $totalVagas ?>
            </strong>

            <br>

            <small class="text-muted">
                Total
            </small>

        </div>


        <div class="col-md-4">

            <strong>
                <?= $vagasLivres ?>
            </strong>

            <br>

            <small class="text-muted">
                Livres
            </small>

        </div>


        <div class="col-md-4">

            <strong>
                <?= $vagasOcupadas ?>
            </strong>

            <br>

            <small class="text-muted">
                Ocupadas
            </small>

        </div>


    </div>

</div>


<?php

require "includes/footer.php";

?>
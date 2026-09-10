<?php

require "../config/banco.php";

$tituloPagina = "Relatórios";


/*
|--------------------------------------------------------------------------
| FILTRO DE DATAS
|--------------------------------------------------------------------------
*/

$dataInicio = $_GET["data_inicio"] ?? "";
$dataFim = $_GET["data_fim"] ?? "";


/*
|--------------------------------------------------------------------------
| CONDIÇÕES DO FILTRO
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


if ($dataInicio != "") {

    $where[] = "date(estacionamentos.entrada) >= ?";
    $params[] = $dataInicio;
}


if ($dataFim != "") {

    $where[] = "date(estacionamentos.entrada) <= ?";
    $params[] = $dataFim;
}


$whereSql = "";

if (count($where) > 0) {

    $whereSql = "WHERE " . implode(
        " AND ",
        $where
    );
}


/*
|--------------------------------------------------------------------------
| HISTÓRICO
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        estacionamentos.id,
        estacionamentos.entrada,
        estacionamentos.saida,
        estacionamentos.valor,

        veiculos.placa,
        veiculos.marca,
        veiculos.modelo,

        clientes.nome AS cliente_nome,

        vagas.numero AS vaga_numero

    FROM estacionamentos

    INNER JOIN veiculos
        ON veiculos.id = estacionamentos.veiculo_id

    LEFT JOIN clientes
        ON clientes.id = veiculos.cliente_id

    INNER JOIN vagas
        ON vagas.id = estacionamentos.vaga_id

    $whereSql

    ORDER BY estacionamentos.entrada DESC
";


$stmt = $db->prepare($sql);
$stmt->execute($params);

$historico = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| ESTATÍSTICAS DO RELATÓRIO
|--------------------------------------------------------------------------
*/

$totalRegistros = count($historico);

$totalSaidas = 0;
$faturamento = 0;
$totalTempoMinutos = 0;


foreach ($historico as $registro) {

    if ($registro["saida"] != null) {

        $totalSaidas++;

        $faturamento += (float) $registro["valor"];


        /*
        | Calcula tempo de permanência
        */

        $entrada = new DateTime(
            $registro["entrada"]
        );

        $saida = new DateTime(
            $registro["saida"]
        );

        $diferenca = $entrada->diff($saida);

        $minutos =
            ($diferenca->days * 24 * 60) +
            ($diferenca->h * 60) +
            $diferenca->i;

        $totalTempoMinutos += $minutos;
    }
}


/*
|--------------------------------------------------------------------------
| TEMPO MÉDIO
|--------------------------------------------------------------------------
*/

$tempoMedio = 0;

if ($totalSaidas > 0) {

    $tempoMedio =
        $totalTempoMinutos / $totalSaidas;
}


$horasMedias = floor(
    $tempoMedio / 60
);

$minutosMedios = round(
    $tempoMedio % 60
);


require "../includes/header.php";

?>


<!-- TÍTULO -->

<div class="mb-4">

    <h1 class="page-title">

        Relatórios

    </h1>

    <p class="page-subtitle">

        Consulte o histórico e o movimento do estacionamento

    </p>

</div>


<!-- FILTRO -->

<div class="form-container mb-4">

    <h4 class="mb-4">

        <i class="bi bi-funnel"></i>

        Filtrar período

    </h4>


    <form method="GET">

        <div class="row g-3 align-items-end">


            <!-- DATA INICIAL -->

            <div class="col-md-4">

                <label class="form-label">

                    Data inicial

                </label>

                <input
                    type="date"
                    name="data_inicio"
                    class="form-control"
                    value="<?= htmlspecialchars($dataInicio) ?>"
                >

            </div>


            <!-- DATA FINAL -->

            <div class="col-md-4">

                <label class="form-label">

                    Data final

                </label>

                <input
                    type="date"
                    name="data_fim"
                    class="form-control"
                    value="<?= htmlspecialchars($dataFim) ?>"
                >

            </div>


            <!-- BOTÕES -->

            <div class="col-md-4">

                <button
                    type="submit"
                    class="btn btn-dark"
                >

                    <i class="bi bi-search"></i>

                    Filtrar

                </button>


                <a
                    href="relatorios.php"
                    class="btn btn-outline-secondary"
                >

                    <i class="bi bi-x-lg"></i>

                    Limpar

                </a>

            </div>

        </div>

    </form>

</div>


<!-- RESUMO -->

<div class="row g-4 mb-4">


    <!-- REGISTROS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-list-check"></i>

            </div>

            <h2>

                <?= $totalRegistros ?>

            </h2>

            <p>

                Registros encontrados

            </p>

        </div>

    </div>


    <!-- SAÍDAS -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-box-arrow-right"></i>

            </div>

            <h2>

                <?= $totalSaidas ?>

            </h2>

            <p>

                Saídas realizadas

            </p>

        </div>

    </div>


    <!-- FATURAMENTO -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-cash-stack"></i>

            </div>

            <h2>

                R$

                <?= number_format(
                    $faturamento,
                    2,
                    ",",
                    "."
                ) ?>

            </h2>

            <p>

                Faturamento

            </p>

        </div>

    </div>


    <!-- TEMPO MÉDIO -->

    <div class="col-md-6 col-xl-3">

        <div class="dashboard-card">

            <div class="icone">

                <i class="bi bi-clock"></i>

            </div>

            <h2>

                <?= $horasMedias ?>h
                <?= $minutosMedios ?>min

            </h2>

            <p>

                Permanência média

            </p>

        </div>

    </div>

</div>


<!-- HISTÓRICO -->

<div class="table-container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">

                <i class="bi bi-clock-history"></i>

                Histórico de estacionamentos

            </h4>

            <small class="text-muted">

                <?= $totalRegistros ?>

                registro(s) encontrado(s)

            </small>

        </div>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>Placa</th>

                    <th>Veículo</th>

                    <th>Cliente</th>

                    <th>Vaga</th>

                    <th>Entrada</th>

                    <th>Saída</th>

                    <th>Valor</th>

                    <th>Status</th>

                </tr>

            </thead>


            <tbody>


                <?php if (count($historico) == 0): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="text-center py-5 text-muted"
                        >

                            <i class="bi bi-inbox fs-1"></i>

                            <br>

                            Nenhum registro encontrado.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($historico as $registro): ?>


                        <tr>


                            <!-- PLACA -->

                            <td>

                                <span class="badge text-bg-dark">

                                    <?= htmlspecialchars(
                                        $registro["placa"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- VEÍCULO -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $registro["marca"]
                                    ) ?>

                                </strong>

                                <br>

                                <small class="text-muted">

                                    <?= htmlspecialchars(
                                        $registro["modelo"]
                                    ) ?>

                                </small>

                            </td>


                            <!-- CLIENTE -->

                            <td>

                                <?= htmlspecialchars(
                                    $registro["cliente_nome"]
                                    ?? "Sem proprietário"
                                ) ?>

                            </td>


                            <!-- VAGA -->

                            <td>

                                <span class="badge text-bg-primary">

                                    Vaga
                                    <?= htmlspecialchars(
                                        $registro["vaga_numero"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- ENTRADA -->

                            <td>

                                <?= date(
                                    "d/m/Y H:i",
                                    strtotime(
                                        $registro["entrada"]
                                    )
                                ) ?>

                            </td>


                            <!-- SAÍDA -->

                            <td>

                                <?php if ($registro["saida"]): ?>

                                    <?= date(
                                        "d/m/Y H:i",
                                        strtotime(
                                            $registro["saida"]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">

                                        —

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- VALOR -->

                            <td>

                                <?php if ($registro["saida"]): ?>

                                    <strong>

                                        R$

                                        <?= number_format(
                                            $registro["valor"],
                                            2,
                                            ",",
                                            "."
                                        ) ?>

                                    </strong>

                                <?php else: ?>

                                    <span class="text-muted">

                                        —

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if ($registro["saida"]): ?>

                                    <span class="badge text-bg-success">

                                        Finalizado

                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-warning">

                                        Estacionado

                                    </span>

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<?php

require "../includes/footer.php";

?>
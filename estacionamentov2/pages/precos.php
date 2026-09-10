<?php

require "../config/banco.php";

$tituloPagina = "Preços";

$mensagem = "";
$tipoMensagem = "";

/*
|--------------------------------------------------------------------------
| ATUALIZAR PREÇOS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $precos = [
        "Hora" => $_POST["hora"] ?? ""
    ];

    $erro = false;

    foreach ($precos as $valor) {

        if (
            $valor === "" ||
            !is_numeric($valor) ||
            (float) $valor < 0
        ) {
            $erro = true;
            break;
        }
    }

    if ($erro) {

        $mensagem = "Informe valores válidos para todos os preços.";
        $tipoMensagem = "danger";

    } else {

        try {

            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE precos
                SET valor = ?
                WHERE tipo = ?
            ");

            foreach ($precos as $tipo => $valor) {

                $stmt->execute([
                    (float) $valor,
                    $tipo
                ]);
            }

            /* Garante que planos antigos não permaneçam no banco. */
            $db->exec("DELETE FROM precos WHERE tipo IN ('Diária', 'Mensal', 'Anual')");

            $db->commit();

            $mensagem = "Preços atualizados com sucesso!";
            $tipoMensagem = "success";

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mensagem = "Erro ao atualizar os preços.";
            $tipoMensagem = "danger";
        }
    }
}

$dadosPrecos = $db
    ->query("
        SELECT tipo, valor
        FROM precos
        ORDER BY id
    ")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$precoHora = $dadosPrecos["Hora"] ?? 8.00;

require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Configuração de preços
    </h1>

    <p class="page-subtitle">
        Configure os valores do estacionamento
    </p>

</div>

<?php if ($mensagem != ""): ?>

    <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show">

        <?= htmlspecialchars($mensagem) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>

<div class="alert alert-light border mb-4">

    <i class="bi bi-info-circle"></i>

    <strong>Como funciona a cobrança:</strong>

    <ul class="mb-0 mt-2">
        <li>A cobrança normal é proporcional ao tempo real estacionado.</li>
        <li>O valor por hora é usado como base, inclusive para minutos e segundos.</li>
        <li>Até 10 minutos de atraso após o tempo combinado não há cobrança adicional.</li>
        <li>Depois da tolerância, os 10 minutos também são cobrados normalmente e o tempo seguinte passa a custar 20% a mais por hora.</li>
    </ul>

</div>

<div class="form-container">

    <div class="mb-4">

        <h4>
            <i class="bi bi-cash-coin"></i>
            Valores do estacionamento
        </h4>

        <p class="text-muted mb-0">
            O estacionamento utiliza cobrança proporcional ao tempo real, com base no valor por hora.
        </p>

    </div>

    <form method="POST">

        <div class="row g-4">

            <div class="col-md-4">

                <label class="form-label">
                    <i class="bi bi-clock"></i>
                    Valor por hora
                </label>

                <div class="input-group">

                    <span class="input-group-text">R$</span>

                    <input
                        type="number"
                        name="hora"
                        class="form-control"
                        value="<?= htmlspecialchars($precoHora) ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>

                <small class="text-muted">
                    Base da cobrança proporcional por tempo.
                </small>

            </div>
</div>

        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-dark"
            >

                <i class="bi bi-check-lg"></i>
                Salvar preços

            </button>

        </div>

    </form>

</div>

<div class="table-container mt-4">

    <h4 class="mb-4">
        <i class="bi bi-receipt"></i>
        Resumo dos valores
    </h4>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Descrição</th>
                </tr>
            </thead>

            <tbody>

                <tr>
                    <td><strong>Hora</strong></td>
                    <td>R$ <?= number_format($precoHora, 2, ",", ".") ?></td>
                    <td>Cobrança proporcional ao tempo real estacionado.</td>
                </tr>
</tbody>

        </table>

    </div>

</div>

<?php
require "../includes/footer.php";
?>

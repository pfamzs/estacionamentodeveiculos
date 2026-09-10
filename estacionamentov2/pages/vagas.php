<?php

require "../config/banco.php";

$tituloPagina = "Vagas";

require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Vagas
    </h1>

    <p class="page-subtitle">
        Visualização das vagas do estacionamento
    </p>

</div>

<div class="row g-4">

    <?php

    $vagas = $db
        ->query("
            SELECT *
            FROM vagas
            ORDER BY numero
        ")
        ->fetchAll(PDO::FETCH_ASSOC);

    ?>

    <?php foreach ($vagas as $vaga): ?>

        <div class="col-6 col-md-4 col-lg-3 col-xl-2">

            <div class="vaga

                <?php

                if ($vaga["status"] == "Livre") {
                    echo "vaga-livre";
                } else {
                    echo "vaga-ocupada";
                }

                ?>

            ">

                <div class="vaga-numero">

                    <?= $vaga["numero"] ?>

                </div>

                <div>

                    <?= htmlspecialchars($vaga["tipo"]) ?>

                </div>

                <small class="text-muted">

                    <?= htmlspecialchars($vaga["tamanho"]) ?>

                </small>

                <div class="mt-2">

                    <?php if ($vaga["status"] == "Livre"): ?>

                        <span class="badge text-bg-success">
                            Livre
                        </span>

                    <?php else: ?>

                        <span class="badge text-bg-danger">
                            Ocupada
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<?php

require "../includes/footer.php";

?>
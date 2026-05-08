<?php
declare(strict_types=1);

session_start();

const PASSWORD_SHA256 = '300037e9b59f75c05cced83152aefe25d5c490f10f796be25bf74d35b504b6fd';

$contentFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'content.json';
$message = '';
$error = '';

function flattenContent(array $data, string $prefix = ''): array
{
    $fields = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            $fields += flattenContent($value, $path);
            continue;
        }

        $fields[$path] = (string) $value;
    }

    return $fields;
}

function setByPath(array &$data, string $path, string $value): void
{
    $keys = explode('.', $path);
    $current = &$data;

    foreach ($keys as $index => $key) {
        $isLast = $index === count($keys) - 1;
        $arrayKey = ctype_digit($key) ? (int) $key : $key;

        if ($isLast) {
            $current[$arrayKey] = $value;
            return;
        }

        if (!isset($current[$arrayKey]) || !is_array($current[$arrayKey])) {
            $current[$arrayKey] = [];
        }

        $current = &$current[$arrayKey];
    }
}

function fieldLabel(string $path): string
{
    $map = [
        'site.title' => 'Título do navegador',
        'site.description' => 'Descrição para buscadores',
        'hero.eyebrow' => 'Chamada acima do título',
        'hero.title' => 'Título principal',
        'hero.copy' => 'Texto principal',
        'hero.primaryCta' => 'Botão principal',
        'hero.secondaryCta' => 'Botão secundário',
        'signals.origin.label' => 'Card origem - rótulo',
        'signals.origin.value' => 'Card origem - valor',
        'signals.strategy.label' => 'Card estratégia - rótulo',
        'signals.strategy.value' => 'Card estratégia - valor',
        'signals.investment.label' => 'Card investimento - rótulo',
        'signals.investment.value' => 'Card investimento - valor',
        'dataroom.label' => 'Dataroom - rótulo',
        'dataroom.title' => 'Dataroom - título',
        'dataroom.copy' => 'Dataroom - texto',
        'dataroom.cta' => 'Dataroom - botão',
        'about.label' => 'Seção quem somos - rótulo',
        'about.title' => 'Seção quem somos - título',
        'about.copy1' => 'Seção quem somos - texto 1',
        'about.copy2' => 'Seção quem somos - texto 2',
        'about.caption' => 'Legenda da imagem',
        'thesis.label' => 'Seção tese - rótulo',
        'thesis.title' => 'Seção tese - título',
        'thesis.copy' => 'Seção tese - texto',
        'brasil.label' => 'Seção Brasil TecPar - rótulo',
        'brasil.title' => 'Seção Brasil TecPar - título',
        'brasil.copy' => 'Seção Brasil TecPar - texto',
        'brasil.evidenceTitle' => 'Bloco evidência - título',
        'brasil.evidenceCopy' => 'Bloco evidência - texto',
        'brasil.evidenceLink' => 'Bloco evidência - link',
        'investors.label' => 'Seção investidores - rótulo',
        'investors.title' => 'Seção investidores - título',
        'investors.copy' => 'Seção investidores - texto',
        'updates.label' => 'Seção atualizações - rótulo',
        'updates.title' => 'Seção atualizações - título',
        'contact.label' => 'Seção contato - rótulo',
        'contact.title' => 'Seção contato - título',
        'contact.copy' => 'Seção contato - texto',
        'contact.email' => 'Contato - email',
        'contact.whatsappRi' => 'Contato - WhatsApp RI',
        'contact.whatsappFinance' => 'Contato - WhatsApp Financeiro',
        'contact.address' => 'Contato - endereço',
        'contact.formButton' => 'Formulário - botão',
        'contact.formNote' => 'Formulário - observação',
        'footer.brand' => 'Rodapé - marca',
        'footer.copy' => 'Rodapé - direitos',
        'footer.privacy' => 'Rodapé - política',
        'footer.terms' => 'Rodapé - termos',
    ];

    if (isset($map[$path])) {
        return $map[$path];
    }

    $label = str_replace(['.', '_'], ' ', $path);
    return ucwords($label);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $hash = hash('sha256', (string) $_POST['password']);

    if (hash_equals(PASSWORD_SHA256, $hash)) {
        $_SESSION['authenticated'] = true;
        header('Location: ./');
        exit;
    }

    $error = 'Senha incorreta.';
}

$isAuthenticated = !empty($_SESSION['authenticated']);

if (!file_exists($contentFile)) {
    $error = 'Arquivo data/content.json não encontrado.';
    $content = [];
} else {
    $json = file_get_contents($contentFile);
    $content = json_decode((string) $json, true);

    if (!is_array($content)) {
        $content = [];
        $error = 'Arquivo data/content.json inválido.';
    }
}

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && is_array($_POST['content'])) {
    $updated = $content;

    foreach ($_POST['content'] as $path => $value) {
        setByPath($updated, (string) $path, trim((string) $value));
    }

    $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($encoded === false) {
        $error = 'Não foi possível preparar os textos para salvar.';
    } elseif (file_put_contents($contentFile, $encoded . PHP_EOL, LOCK_EX) === false) {
        $error = 'Não foi possível salvar. Verifique permissão de escrita em data/content.json.';
    } else {
        $content = $updated;
        $message = 'Textos salvos com sucesso.';
    }
}

$fields = flattenContent($content);
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Gaúcha TecPar</title>
    <style>
      :root {
        --ink: #0a1628;
        --navy: #102f55;
        --green: #2f9d75;
        --paper: #f5f7f6;
        --line: #d3dde3;
        --muted: #617184;
        --white: #ffffff;
      }

      * { box-sizing: border-box; }

      body {
        margin: 0;
        font-family: "Segoe UI", Arial, sans-serif;
        color: var(--ink);
        background: var(--paper);
      }

      .admin-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px min(5vw, 56px);
        background: var(--ink);
        color: var(--white);
      }

      .admin-header h1 {
        margin: 0;
        font-size: 22px;
      }

      .admin-header a {
        color: var(--white);
        text-decoration: none;
        border-bottom: 1px solid currentColor;
      }

      main {
        width: min(980px, calc(100% - 36px));
        margin: 0 auto;
        padding: 42px 0 72px;
      }

      .panel {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--white);
        padding: 28px;
        box-shadow: 0 18px 44px rgba(10, 22, 40, 0.1);
      }

      .panel + .panel { margin-top: 18px; }

      label {
        display: grid;
        gap: 8px;
        margin-bottom: 18px;
        color: var(--navy);
        font-weight: 700;
      }

      .field-key {
        color: var(--muted);
        font-size: 12px;
        font-weight: 500;
      }

      input,
      textarea {
        width: 100%;
        border: 1px solid #cbd6dd;
        border-radius: 8px;
        padding: 12px 13px;
        color: var(--ink);
        font: inherit;
      }

      textarea {
        min-height: 118px;
        resize: vertical;
      }

      button {
        min-height: 48px;
        border: 0;
        border-radius: 8px;
        padding: 12px 20px;
        background: var(--green);
        color: var(--white);
        font-weight: 800;
        cursor: pointer;
      }

      .notice {
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 18px;
        background: #e4f3ee;
        color: #155d43;
        font-weight: 700;
      }

      .error {
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 18px;
        background: #ffe8e6;
        color: #8b1e15;
        font-weight: 700;
      }

      .actions {
        position: sticky;
        bottom: 0;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 18px;
        background: linear-gradient(180deg, rgba(245, 247, 246, 0), var(--paper) 38%);
      }

      @media (max-width: 640px) {
        .admin-header {
          align-items: flex-start;
          flex-direction: column;
        }

        .panel {
          padding: 20px;
        }
      }
    </style>
  </head>
  <body>
    <header class="admin-header">
      <h1>Admin Gaúcha TecPar</h1>
      <div>
        <a href="../" target="_blank" rel="noreferrer">Ver site</a>
        <?php if ($isAuthenticated): ?>
          &nbsp;·&nbsp;<a href="?logout=1">Sair</a>
        <?php endif; ?>
      </div>
    </header>

    <main>
      <?php if ($message !== ''): ?>
        <div class="notice"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if (!$isAuthenticated): ?>
        <section class="panel">
          <form method="post">
            <label>
              Senha
              <input type="password" name="password" autocomplete="current-password" required autofocus>
            </label>
            <button type="submit">Entrar</button>
          </form>
        </section>
      <?php else: ?>
        <form method="post">
          <section class="panel">
            <?php foreach ($fields as $path => $value): ?>
              <label>
                <?= htmlspecialchars(fieldLabel((string) $path), ENT_QUOTES, 'UTF-8') ?>
                <span class="field-key"><?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (strlen((string) $value) > 80 || strpos((string) $value, "\n") !== false): ?>
                  <textarea name="content[<?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?>]"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php else: ?>
                  <input name="content[<?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </section>

          <div class="actions">
            <button type="submit">Salvar textos</button>
          </div>
        </form>
      <?php endif; ?>
    </main>
  </body>
</html>

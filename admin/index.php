<?php
declare(strict_types=1);

session_start();

const PASSWORD_SHA256 = '300037e9b59f75c05cced83152aefe25d5c490f10f796be25bf74d35b504b6fd';

$contentFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'content.json';
$message = '';
$error = '';

function defaultUpdateLinks(): array
{
    return [
        0 => 'https://teletime.com.br/06/03/2026/puxada-por-aquisicoes-brasil-tecpar-tem-lucro-de-r-72-milhoes-em-2025/',
        1 => 'https://teletime.com.br/20/02/2026/brasil-tecpar-compra-operacao-de-banda-larga-da-ligga/',
        2 => 'https://www.brasiltecpar.com.br/post/brasil-tecpar-conclui-aquisic-a-o-da-sempre-internet-e-ultrapassa-1-2-milha-o-de-clientes-conectados',
    ];
}

function defaultTeamContent(): array
{
    $placeholder = 'assets/team-placeholder.svg';

    return [
        'label' => 'Equipe',
        'title' => 'Diretoria e Conselho de Administração',
        'copy' => 'Conheça a liderança responsável pela governança, estratégia e disciplina financeira da companhia.',
        'executives' => [
            'label' => 'Diretoria',
            'members' => [
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Diretor Presidente',
                    'role' => 'Diretor Presidente',
                    'caption' => 'Liderança executiva',
                    'bio' => 'Inclua aqui uma breve descrição sobre trajetória, responsabilidades e foco de atuação.',
                    'linkText' => '',
                    'link' => '',
                ],
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Diretor Vice-Presidente e RI',
                    'role' => 'Diretor Vice-Presidente e RI',
                    'caption' => 'Relações com investidores',
                    'bio' => 'Inclua aqui uma breve descrição sobre trajetória, responsabilidades e foco de atuação.',
                    'linkText' => '',
                    'link' => '',
                ],
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Diretor Financeiro',
                    'role' => 'Diretor Financeiro',
                    'caption' => 'Gestão financeira',
                    'bio' => 'Inclua aqui uma breve descrição sobre trajetória, responsabilidades e foco de atuação.',
                    'linkText' => '',
                    'link' => '',
                ],
            ],
        ],
        'board' => [
            'label' => 'Conselho de Administração',
            'members' => [
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Conselheiro 1',
                    'role' => 'Conselheiro de Administração',
                    'caption' => 'Conselho de Administração',
                    'bio' => 'Inclua aqui uma breve descrição sobre experiência e contribuição para a governança.',
                    'linkText' => '',
                    'link' => '',
                ],
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Conselheiro 2',
                    'role' => 'Conselheiro de Administração',
                    'caption' => 'Conselho de Administração',
                    'bio' => 'Inclua aqui uma breve descrição sobre experiência e contribuição para a governança.',
                    'linkText' => '',
                    'link' => '',
                ],
                [
                    'photo' => $placeholder,
                    'name' => 'Nome do Conselheiro 3',
                    'role' => 'Conselheiro de Administração',
                    'caption' => 'Conselho de Administração',
                    'bio' => 'Inclua aqui uma breve descrição sobre experiência e contribuição para a governança.',
                    'linkText' => '',
                    'link' => '',
                ],
            ],
        ],
    ];
}

function mergeMissingDefaults(array $data, array $defaults): array
{
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $data) || (is_array($value) && !is_array($data[$key]))) {
            $data[$key] = $value;
            continue;
        }

        if (is_array($data[$key]) && is_array($value)) {
            $data[$key] = mergeMissingDefaults($data[$key], $value);
        }
    }

    return $data;
}

function looksLikeUrl(string $value): bool
{
    return preg_match('/^https?:\/\//i', trim($value)) === 1;
}

function isTeamPhotoPath(string $path): bool
{
    return preg_match('/^team\.(executives|board)\.members\.\d+\.photo$/', $path) === 1;
}

function adminPreviewSrc(string $value): string
{
    $value = trim($value);

    if ($value === '' || looksLikeUrl($value) || strpos($value, '/') === 0) {
        return $value;
    }

    return '../' . ltrim($value, '/');
}

function ensureContentDefaults(array $data): array
{
    $defaultLinks = defaultUpdateLinks();

    $data = mergeMissingDefaults($data, [
        'brasil' => [
            'evidenceUrl' => 'https://www.brasiltecpar.com.br/relatoriosanuais',
        ],
        'team' => defaultTeamContent(),
    ]);

    if (!isset($data['brasil']['evidenceUrl']) || trim((string) $data['brasil']['evidenceUrl']) === '') {
        $data['brasil']['evidenceUrl'] = 'https://www.brasiltecpar.com.br/relatoriosanuais';
    }

    if (!isset($data['updates']['items']) || !is_array($data['updates']['items'])) {
        return $data;
    }

    foreach ($data['updates']['items'] as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        if (!isset($item['link']) || trim((string) $item['link']) === '') {
            $linkText = (string) ($item['linkText'] ?? '');
            $item['link'] = looksLikeUrl($linkText) ? trim($linkText) : ($defaultLinks[$index] ?? '');
        }

        $data['updates']['items'][$index] = $item;
    }

    return $data;
}

function sendBackup(string $contentFile): void
{
    if (!is_file($contentFile)) {
        http_response_code(404);
        echo 'Arquivo de conteudo nao encontrado.';
        exit;
    }

    $filename = 'backup-gauchatecpar-' . date('Ymd-His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($contentFile));
    header('Cache-Control: no-store');
    readfile($contentFile);
    exit;
}

function saveContentFile(string $contentFile, array $content): bool
{
    $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($encoded === false) {
        return false;
    }

    return file_put_contents($contentFile, $encoded . PHP_EOL, LOCK_EX) !== false;
}

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
        'brasil.evidenceLink' => 'Bloco evidência - texto do link',
        'brasil.evidenceUrl' => 'Bloco evidência - URL do link',
        'team.label' => 'Equipe - rótulo',
        'team.title' => 'Equipe - título',
        'team.copy' => 'Equipe - texto',
        'team.executives.label' => 'Equipe - título da diretoria',
        'team.board.label' => 'Equipe - título do conselho',
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

    if (preg_match('/^updates\.items\.(\d+)\.(date|title|copy|linkText|link)$/', $path, $matches) === 1) {
        $number = ((int) $matches[1]) + 1;
        $labels = [
            'date' => 'Noticia ' . $number . ' - data',
            'title' => 'Noticia ' . $number . ' - titulo',
            'copy' => 'Noticia ' . $number . ' - texto',
            'linkText' => 'Noticia ' . $number . ' - texto do botao',
            'link' => 'Noticia ' . $number . ' - link',
        ];

        return $labels[$matches[2]];
    }

    if (preg_match('/^team\.(executives|board)\.members\.(\d+)\.(photo|name|role|caption|bio|description|linkText|link)$/', $path, $matches) === 1) {
        $group = $matches[1] === 'executives' ? 'Diretoria' : 'Conselho';
        $number = ((int) $matches[2]) + 1;
        $labels = [
            'photo' => 'foto',
            'name' => 'nome',
            'role' => 'cargo',
            'caption' => 'legenda',
            'bio' => 'descrição',
            'description' => 'descrição',
            'linkText' => 'texto do link',
            'link' => 'link',
        ];

        return $group . ' ' . $number . ' - ' . $labels[$matches[3]];
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

if ($isAuthenticated && isset($_GET['backup'])) {
    sendBackup($contentFile);
}

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
    if (is_array($content)) {
        $content = ensureContentDefaults($content);
    }
}

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $uploadedFile = $_FILES['backup_file'] ?? null;

    if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Nao foi possivel ler o arquivo de backup enviado.';
    } else {
        $json = file_get_contents((string) $uploadedFile['tmp_name']);
        $restored = json_decode((string) $json, true);

        if (!is_array($restored)) {
            $error = 'Backup invalido. Envie um arquivo JSON exportado pelo admin.';
        } elseif (!saveContentFile($contentFile, $restored)) {
            $error = 'Nao foi possivel restaurar. Verifique permissao de escrita em data/content.json.';
        } else {
            $content = ensureContentDefaults($restored);
            $message = 'Backup restaurado com sucesso.';
        }
    }
}

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && is_array($_POST['content'])) {
    $updated = $content;
    $uploadErrors = [];

    foreach ($_POST['content'] as $path => $value) {
        setByPath($updated, (string) $path, trim((string) $value));
    }

    $imageUploads = $_FILES['image_uploads'] ?? null;

    if (is_array($imageUploads) && isset($imageUploads['error']) && is_array($imageUploads['error'])) {
        $teamAssetsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'team';

        foreach ($imageUploads['error'] as $path => $uploadError) {
            $path = (string) $path;

            if (!isTeamPhotoPath($path) || (int) $uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ((int) $uploadError !== UPLOAD_ERR_OK) {
                $uploadErrors[] = 'Falha ao enviar a foto de ' . fieldLabel($path) . '.';
                continue;
            }

            $tmpName = (string) ($imageUploads['tmp_name'][$path] ?? '');
            $fileSize = (int) ($imageUploads['size'][$path] ?? 0);

            if ($fileSize > 5 * 1024 * 1024) {
                $uploadErrors[] = 'A foto de ' . fieldLabel($path) . ' deve ter no máximo 5 MB.';
                continue;
            }

            $imageInfo = @getimagesize($tmpName);
            $allowedTypes = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
            ];

            if (defined('IMAGETYPE_WEBP')) {
                $allowedTypes[IMAGETYPE_WEBP] = 'webp';
            }

            if (!is_array($imageInfo) || !isset($allowedTypes[$imageInfo[2]])) {
                $uploadErrors[] = 'A foto de ' . fieldLabel($path) . ' deve ser JPG, PNG ou WebP.';
                continue;
            }

            if (!is_dir($teamAssetsDir) && !mkdir($teamAssetsDir, 0755, true)) {
                $uploadErrors[] = 'Não foi possível criar a pasta de fotos da equipe.';
                continue;
            }

            $safeBase = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $path));
            $fileName = trim($safeBase, '-') . '-' . date('YmdHis') . '.' . $allowedTypes[$imageInfo[2]];
            $destination = $teamAssetsDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($tmpName, $destination)) {
                $uploadErrors[] = 'Não foi possível salvar a foto de ' . fieldLabel($path) . '.';
                continue;
            }

            setByPath($updated, $path, 'assets/team/' . $fileName);
        }
    }

    $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($uploadErrors !== []) {
        $error = implode(' ', $uploadErrors);
    } elseif ($encoded === false) {
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

      .image-field {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
      }

      .image-field img {
        width: 96px;
        aspect-ratio: 4 / 5;
        object-fit: cover;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #eef3f4;
      }

      .image-controls {
        display: grid;
        gap: 10px;
      }

      .help-text {
        margin: 0;
        color: var(--muted);
        font-size: 12px;
        font-weight: 500;
        line-height: 1.45;
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

      .button-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        border-radius: 8px;
        padding: 12px 20px;
        background: var(--navy);
        color: var(--white);
        font-weight: 800;
        text-decoration: none;
      }

      .tools-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
      }

      .tool-card {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 18px;
        background: #fbfcfc;
      }

      .tool-card h2 {
        margin: 0 0 8px;
        color: var(--navy);
        font-size: 18px;
      }

      .tool-card p {
        margin: 0 0 14px;
        color: var(--muted);
        line-height: 1.45;
      }

      input[type="file"] {
        padding: 10px;
        background: var(--white);
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

        .tools-grid {
          grid-template-columns: 1fr;
        }

        .image-field {
          grid-template-columns: 1fr;
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
        <section class="panel">
          <div class="tools-grid">
            <div class="tool-card">
              <h2>Backup</h2>
              <p>Baixe uma copia completa dos textos e numeros atuais antes de alterar o site.</p>
              <a class="button-link" href="?backup=1">Baixar backup</a>
            </div>
            <div class="tool-card">
              <h2>Restauro</h2>
              <p>Envie um arquivo JSON de backup para restaurar os textos e numeros do site.</p>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="restore_backup" value="1">
                <label>
                  Arquivo de backup
                  <input type="file" name="backup_file" accept="application/json,.json" required>
                </label>
                <button type="submit">Restaurar backup</button>
              </form>
            </div>
          </div>
        </section>

        <form method="post" enctype="multipart/form-data">
          <section class="panel">
            <?php foreach ($fields as $path => $value): ?>
              <label>
                <?= htmlspecialchars(fieldLabel((string) $path), ENT_QUOTES, 'UTF-8') ?>
                <span class="field-key"><?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (isTeamPhotoPath((string) $path)): ?>
                  <div class="image-field">
                    <?php if (trim((string) $value) !== ''): ?>
                      <img src="<?= htmlspecialchars(adminPreviewSrc((string) $value), ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php endif; ?>
                    <div class="image-controls">
                      <input name="content[<?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
                      <input type="file" name="image_uploads[<?= htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') ?>]" accept="image/jpeg,image/png,image/webp">
                      <p class="help-text">Envie JPG, PNG ou WebP até 5 MB, ou informe manualmente uma URL/caminho da foto.</p>
                    </div>
                  </div>
                <?php elseif (strlen((string) $value) > 80 || strpos((string) $value, "\n") !== false): ?>
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

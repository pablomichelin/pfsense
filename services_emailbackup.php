<?php
require_once("guiconfig.inc");

// Função para registrar mensagens de log
function log_message($message) {
    $log_file = "/var/log/scriptbackupemail.log";
    $timestamp = date("[Y-m-d H:i:s] ");
    file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
}

// Variável para mensagens de feedback
$feedback_message = "";

if ($_POST && $_POST['action'] === 'process') {
    // Processar o formulário e executar os comandos
    $subject = htmlspecialchars($_POST['subject']);
    $receiver_email = htmlspecialchars($_POST['receiver_email']);
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);

    // Corpo do e-mail padrão
    $body = "Esse e-mail possui um Backup Pfsense";

    // Passo 1: Criar arquivo
    $filename = "/root/envia_email.py";
    $script_content = <<<SCRIPT
import email, smtplib, ssl
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
import sys

subject = "$subject"
body = "$body"
receiver_email = "$receiver_email"
sender_email = "$username"

message = MIMEMultipart()
message["Subject"] = subject
message["From"] = sender_email
message["To"] = receiver_email

message.attach(MIMEText(body, "plain"))

filename = "/conf/config.xml"

try:
    with open(filename, "rb") as attachment:
        part = MIMEBase("application", "octet-stream")
        part.set_payload(attachment.read())

    encoders.encode_base64(part)

    part.add_header(
        "Content-Disposition",
        f"attachment; filename={filename}",
    )

    message.attach(part)
    text = message.as_string()

    username = '$username'
    password = '$password'
    with smtplib.SMTP('smtp.gmail.com', 587) as server:
        server.starttls()
        server.login(username, password)
        server.sendmail(sender_email, receiver_email, message.as_string())
except Exception as e:
    sys.stderr.write(str(e))
    sys.exit(1)
SCRIPT;

    if (!file_put_contents($filename, $script_content)) {
        log_message("Falha ao criar o arquivo: $filename");
    } else {
        log_message("Arquivo criado: $filename");

        // Passo 2: Criptografar arquivo
        $compile_command = "python3.11 -OO -c \"import py_compile; py_compile.compile('/root/envia_email.py', cfile='/root/envia_email.pyc')\" 2>&1";
        shell_exec($compile_command);
        $exit_code = shell_exec("echo $?");

        if (trim($exit_code) !== "0") {
            log_message("Falha ao compilar o arquivo Python: $filename");
        } else {
            log_message("Arquivo Python compilado com sucesso.");

            $compiled_filename = "/root/" . basename($filename, ".py") . ".pyc";
            if (!file_exists($compiled_filename)) {
                log_message("Falha ao encontrar o arquivo compilado: $compiled_filename");
            } else {
                log_message("Arquivo compilado encontrado: $compiled_filename");

                // Passo 3: Deletar arquivo original
                unlink($filename);
                log_message("Arquivo original excluído: $filename");
            }
        }
    }

    // Passo 4: Gerar comando do cron
    $cron_command = "/usr/local/bin/python3.11 /root/envia_email.pyc";
}

// Validação do arquivo Config.XML
if ($_POST && $_POST['action'] === 'validate') {
    $config_file = "/conf/config.xml";

    if (!file_exists($config_file)) {
        $feedback_message = "Erro: Arquivo config.xml não encontrado.";
        log_message($feedback_message);
    } elseif (!simplexml_load_file($config_file)) {
        $feedback_message = "Erro: Arquivo config.xml é inválido.";
        log_message($feedback_message);
    } else {
        $feedback_message = "Sucesso: Arquivo config.xml validado com sucesso.";
        log_message($feedback_message);
    }
}

// Teste de envio de backup por e-mail
if ($_POST && $_POST['action'] === 'test_email') {
    $test_email_command = "/usr/local/bin/python3.11 /root/envia_email.pyc 2>&1";
    $test_email_output = shell_exec($test_email_command);
    $exit_code = shell_exec("echo $?");

    if (trim($exit_code) === "0") {
        $feedback_message = "Sucesso: E-mail de teste enviado com sucesso.";
        log_message($feedback_message);
    } else {
        $feedback_message = "Erro: Falha ao enviar o e-mail de teste. Saída: " . htmlspecialchars($test_email_output);
        log_message($feedback_message);
    }
}

// Excluir arquivo gerado
if ($_POST && isset($_POST['delete_file'])) {
    $file_to_delete = htmlspecialchars($_POST['delete_file']);
    $file_path = "/root/" . basename($file_to_delete);

    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            $feedback_message = "Sucesso: Arquivo \"$file_to_delete\" deletado.";
            log_message($feedback_message);
        } else {
            $feedback_message = "Erro: Não foi possível deletar o arquivo \"$file_to_delete\".";
            log_message($feedback_message);
        }
    } else {
        $feedback_message = "Erro: Arquivo \"$file_to_delete\" não encontrado.";
        log_message($feedback_message);
    }
}

// Configurar agendamento de backup
if ($_POST && $_POST['action'] === 'schedule') {
    $schedule_time = htmlspecialchars($_POST['schedule_time']);
    $hour = htmlspecialchars($_POST['hour'] ?? "*");
    $minute = htmlspecialchars($_POST['minute'] ?? "0");
    $day = htmlspecialchars($_POST['day'] ?? "*");
    $month = htmlspecialchars($_POST['month'] ?? "*");
    $weekday = htmlspecialchars($_POST['weekday'] ?? "*");

    // Adicionar entrada ao config.xml do pfSense
    $cron_entry = [
        'minute' => $minute,
        'hour' => $hour,
        'mday' => $day,
        'month' => $month,
        'wday' => $weekday,
        'who' => 'root',
        'command' => "/usr/local/bin/python3.11 /root/envia_email.pyc",
    ];

    if (!is_array($config['cron']['item'])) {
        $config['cron']['item'] = [];
    }

    $config['cron']['item'][] = $cron_entry;
    write_config("Adicionado agendamento de backup por e-mail.");
    $feedback_message = "Sucesso: Agendamento de backup configurado.";
    log_message($feedback_message);
}
?>

<?php
$pgtitle = array("Services", "Email Backup");
include("head.inc");
?>

<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Configuração de Backup por E-mail</h2></div>
    <div class="panel-body">
        <form method="post">
            <!-- Campos para inserir informações -->
            <input name="subject" type="text" class="form-control" placeholder="Assunto" required><br>
            <input name="receiver_email" type="email" class="form-control" placeholder="E-mail do Destinatário" required><br>
            <input name="username" type="text" class="form-control" placeholder="Usuário SMTP" required><br>
            <input name="password" type="password" class="form-control" placeholder="Senha SMTP" required>
            <small class="form-text text-muted">
                <strong>Nota:</strong> Se você estiver usando o Gmail e a autenticação de dois fatores estiver ativada, será necessário gerar uma <a href="https://support.google.com/accounts/answer/185833" target="_blank">senha de aplicativo</a> para permitir o envio de e-mails.
            </small>
            <br>

            <!-- Botão para criar arquivo, criptografar e executar -->
            <button type="submit" name="action" value="process" class="btn btn-primary">Criar e Enviar Backup</button>
        </form>
    </div>
</div>

<!-- Mensagem de Feedback -->
<?php if ($feedback_message): ?>
<div class="alert alert-info">
    <?= htmlspecialchars($feedback_message) ?>
</div>
<?php endif; ?>

<!-- Botão para validar o arquivo config.xml -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Validar Config.XML</h2></div>
    <div class="panel-body">
        <form method="post">
            <button type="submit" name="action" value="validate" class="btn btn-warning">Validar Config.XML</button>
        </form>
    </div>
</div>

<!-- Botão para testar envio de backup por e-mail -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Testar Envio de Backup por E-mail</h2></div>
    <div class="panel-body">
        <form method="post">
            <button type="submit" name="action" value="test_email" class="btn btn-info">Testar Envio de Backup</button>
        </form>
    </div>
</div>

<!-- Nova seção para exibir os arquivos gerados -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Arquivos Gerados</h2></div>
    <div class="panel-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nome do Arquivo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Diretório onde os backups são gerados
                $backup_dir = "/root/";
                $files = scandir($backup_dir);

                foreach ($files as $file) {
                    // Exibir apenas arquivos .pyc
                    if (strpos($file, '.pyc') !== false) {
                        echo "<tr>
                                <td>" . htmlspecialchars($file) . "</td>
                                <td>
                                    <form method=\"post\" style=\"display:inline;\">
                                        <input type=\"hidden\" name=\"delete_file\" value=\"" . htmlspecialchars($file) . "\">
                                        <button type=\"submit\" class=\"btn btn-danger btn-sm\">Deletar</button>
                                    </form>
                                </td>
                              </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Nova seção para configurar agendamento -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Agendamento de Backup</h2></div>
    <div class="panel-body">
        <form method="post">
            <div class="form-group">
                <label for="schedule_time">Agendar Backup Automático:</label>
                <select name="schedule_time" class="form-control" onchange="toggleScheduleOptions(this.value)">
                    <option value="hourly">De hora em hora</option>
                    <option value="daily">Diariamente</option>
                    <option value="weekly">Semanalmente</option>
                    <option value="monthly">Mensalmente</option>
                </select>
            </div>

            <div id="schedule_options" style="display:none;">
                <div class="form-group">
                    <label for="minute">Minuto:</label>
                    <input name="minute" type="number" class="form-control" placeholder="0-59">
                </div>
                <div class="form-group">
                    <label for="hour">Hora:</label>
                    <input name="hour" type="number" class="form-control" placeholder="0-23">
                </div>
                <div class="form-group" id="day_option" style="display:none;">
                    <label for="day">Dia:</label>
                    <input name="day" type="number" class="form-control" placeholder="1-31">
                </div>
                <div class="form-group" id="weekday_option" style="display:none;">
                    <label for="weekday">Dia da Semana (0-6, onde 0 é domingo):</label>
                    <input name="weekday" type="number" class="form-control" placeholder="0-6">
                </div>
            </div>

            <button type="submit" name="action" value="schedule" class="btn btn-success">Salvar Agendamento</button>
        </form>
    </div>
</div>

<script>
function toggleScheduleOptions(value) {
    document.getElementById('schedule_options').style.display = 'block';
    document.getElementById('day_option').style.display = (value === 'daily' || value === 'monthly') ? 'block' : 'none';
    document.getElementById('weekday_option').style.display = (value === 'weekly') ? 'block' : 'none';
}
</script>

<!-- Nova seção para estatísticas -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Estatísticas de Uso</h2></div>
    <div class="panel-body">
        <?php
        $backup_files = glob("/root/*.pyc");
        $disk_free = disk_free_space("/root");
        echo "Backups armazenados: " . count($backup_files) . "<br>";
        echo "Espaço disponível: " . round($disk_free / (1024 * 1024 * 1024), 2) . " GB<br>";
        ?>
    </div>
</div>

<!-- Nova seção para exibição do log -->
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Pré-visualização do Log</h2></div>
    <div class="panel-body">
        <pre style="background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; height: 200px; overflow-y: scroll;">
        <?php
        $log_file = "/var/log/scriptbackupemail.log";
        if (file_exists($log_file)) {
            echo htmlspecialchars(file_get_contents($log_file));
        } else {
            echo "Arquivo de log não encontrado.";
        }
        ?>
        </pre>
    </div>
</div>

<?php
include("foot.inc");
?>

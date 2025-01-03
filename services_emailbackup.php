<?php
require_once("guiconfig.inc");

// Função para registrar mensagens de log
function log_message($message) {
    $log_file = "/var/log/scriptbackupemail.log";
    $timestamp = date("[Y-m-d H:i:s] ");
    file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
}

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

with open(filename, "rb") as attachment:
    part = MIMEBase("application", "octet-stream")
    part.set_payload(attachment.read())

encoders.encode_base64(part)

part.add_header(
    "Content-Disposition",
    "attachment; filename= " + filename,
)

message.attach(part)
text = message.as_string()

username = '$username'
password = '$password'
s = smtplib.SMTP('smtp.gmail.com:587')
s.starttls()
s.login(username, password)
s.sendmail(sender_email, receiver_email, message.as_string())
s.quit()
SCRIPT;

    if (!file_put_contents($filename, $script_content)) {
        log_message("Falha ao criar o arquivo: $filename");
    } else {
        log_message("Arquivo criado: $filename");

        // Passo 2: Criptografar arquivo
        $compile_command = "python3.11 -OO -c \"import py_compile; py_compile.compile('/root/envia_email.py', cfile='/root/envia_email.pyc')\" 2>&1";
        $log_compile = shell_exec($compile_command);
        if (!$log_compile) {
            log_message("Falha ao compilar o arquivo Python: $filename");
        } else {
            log_message("Log de compilação: $log_compile");

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

// Deletar arquivo se solicitado
if ($_POST && isset($_POST['delete_file'])) {
    $file_to_delete = htmlspecialchars($_POST['delete_file']);
    $file_path = "/root/" . basename($file_to_delete);

    if (file_exists($file_path)) {
        unlink($file_path);
        log_message("Arquivo deletado: $file_path");
    } else {
        log_message("Tentativa de deletar arquivo inexistente: $file_path");
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
    log_message("Agendamento de backup configurado e registrado no config.xml.");
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
            <input name="password" type="password" class="form-control" placeholder="Senha SMTP" required><br>

            <!-- Botão para criar arquivo, criptografar e executar -->
            <button type="submit" name="action" value="process" class="btn btn-primary">Criar e Enviar Backup</button>
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

<?php
include("foot.inc");
?>

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

<?php
include("foot.inc");
?>

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

    // Passo 3: Deletar arquivo original
    unlink($filename);
    log_message("Arquivo original excluído: $filename");

    // Passo 4: Gerar comando do cron
    $cron_command = "/usr/local/bin/python3.11 /root/envia_email.pyc";
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

            <!-- Campo para exibir o comando do cron -->
            <div class="form-group">
                <label for="cron_command">Comando para Adicionar ao Cron:</label>
                <textarea id="cron_command" class="form-control" rows="3" readonly><?php echo $cron_command; ?></textarea>
            </div>
        </form>
    </div>
</div>

<?php
include("foot.inc");
?>

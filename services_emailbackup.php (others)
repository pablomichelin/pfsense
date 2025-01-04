<?php
require_once("guiconfig.inc");

// ==================================================
// ================ FUNÇÕES AUXILIARES =============
// ==================================================

// Função para registrar mensagens de log
function log_message($message) {
    $log_file = "/var/log/scriptbackupemail.log";
    $timestamp = date("[Y-m-d H:i:s] ");
    file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
}

// Função para compilar o script Python com checagem de retorno
function compile_python_script($source, $compiled) {
    // Comando para compilar usando py_compile
    $compile_command = "python3.11 -OO -c \"import py_compile; py_compile.compile('$source', cfile='$compiled')\"";
    
    // Captura saída e código de retorno
    $output = array();
    $return_var = 0;
    exec($compile_command . " 2>&1", $output, $return_var);
    
    return array(
        'success' => ($return_var === 0),
        'output'  => implode("\n", $output)
    );
}

// Função para executar o script Python e verificar retorno
function run_python_script($pyc_file) {
    $command = "/usr/local/bin/python3.11 $pyc_file";
    $output = array();
    $return_var = 0;
    exec($command . " 2>&1", $output, $return_var);
    return array(
        'success' => ($return_var === 0),
        'output'  => implode("\n", $output)
    );
}

// ==================================================
// =============== PROCESSAMENTO POST ===============
// ==================================================
$feedback_message = "";
$cron_command = "/usr/local/bin/python3.11 /root/envia_email.pyc";

if ($_POST) {
    // Detecta ação
    $action = $_POST['action'] ?? '';

    // --------------------------------------------------
    // 1. Criar e processar script .py (action = 'process')
    // --------------------------------------------------
    if ($action === 'process') {
        $subject         = htmlspecialchars($_POST['subject']);
        $receiver_email  = htmlspecialchars($_POST['receiver_email']);
        $username        = htmlspecialchars($_POST['username']);
        $password        = htmlspecialchars($_POST['password']);
        
        // Novos campos para aceitar qualquer provedor
        $smtp_host       = htmlspecialchars($_POST['smtp_host']);
        $smtp_port       = (int)$_POST['smtp_port'];
        $smtp_encryption = htmlspecialchars($_POST['smtp_encryption']); // 'none', 'ssl', ou 'starttls'

        // Corpo do e-mail padrão
        $body = "Esse e-mail possui um Backup Pfsense";

        // Passo 1: Criar arquivo .py
        $filename = "/root/envia_email.py";
        $script_content = <<<SCRIPT
import smtplib, ssl
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
import sys

subject = "$subject"
body = "$body"
receiver_email = "$receiver_email"
sender_email = "$username"
smtp_host = "$smtp_host"
smtp_port = $smtp_port
smtp_encryption = "$smtp_encryption"
password = "$password"

message = MIMEMultipart()
message["Subject"] = subject
message["From"] = sender_email
message["To"] = receiver_email

message.attach(MIMEText(body, "plain"))

filename = "/conf/config.xml"

try:
    # Anexa o config.xml
    with open(filename, "rb") as attachment:
        part = MIMEBase("application", "octet-stream")
        part.set_payload(attachment.read())
    encoders.encode_base64(part)
    part.add_header("Content-Disposition", f"attachment; filename={filename}")
    message.attach(part)

    # Conectar ao servidor SMTP
    if smtp_encryption == "ssl":
        # Conexão SSL (porta padrão 465)
        context = ssl.create_default_context()
        with smtplib.SMTP_SSL(smtp_host, smtp_port, context=context) as server:
            server.login(sender_email, password)
            server.sendmail(sender_email, receiver_email, message.as_string())
    else:
        # Conexão normal ou STARTTLS
        with smtplib.SMTP(smtp_host, smtp_port) as server:
            if smtp_encryption == "starttls":
                context = ssl.create_default_context()
                server.starttls(context=context)
            server.login(sender_email, password)
            server.sendmail(sender_email, receiver_email, message.as_string())

except Exception as e:
    sys.stderr.write(str(e))
    sys.exit(1)
SCRIPT;

        if (!file_put_contents($filename, $script_content)) {
            $feedback_message = "Falha ao criar o arquivo: $filename";
            log_message($feedback_message);
        } else {
            log_message("Arquivo criado: $filename");

            // Passo 2: Compilar script .py para .pyc
            $compiled_filename = "/root/envia_email.pyc";
            $comp_result = compile_python_script($filename, $compiled_filename);

            if (!$comp_result['success']) {
                $feedback_message = "Falha ao compilar o arquivo Python: " . htmlspecialchars($comp_result['output']);
                log_message($feedback_message);
            } else {
                log_message("Arquivo Python compilado com sucesso.");

                if (!file_exists($compiled_filename)) {
                    $feedback_message = "Falha ao encontrar o arquivo compilado: $compiled_filename";
                    log_message($feedback_message);
                } else {
                    log_message("Arquivo compilado encontrado: $compiled_filename");

                    // Passo 3: Deletar arquivo original
                    if (file_exists($filename)) {
                        unlink($filename);
                        log_message("Arquivo original excluído: $filename");
                    }
                }
            }
        }

        // Passo 4: Comando cron (mantido para exibir ou usar depois)
        $cron_command = "/usr/local/bin/python3.11 /root/envia_email.pyc";
    }

    // --------------------------------------------------
    // 2. Validação do arquivo config.xml (action = 'validate')
    // --------------------------------------------------
    if ($action === 'validate') {
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

    // --------------------------------------------------
    // 3. Teste de envio de backup por e-mail (action = 'test_email')
    // --------------------------------------------------
    if ($action === 'test_email') {
        // Executa o .pyc gerado
        $compiled_filename = "/root/envia_email.pyc";
        if (!file_exists($compiled_filename)) {
            $feedback_message = "Erro: Arquivo compilado não encontrado. Crie primeiro o script.";
            log_message($feedback_message);
        } else {
            $result = run_python_script($compiled_filename);
            if ($result['success']) {
                $feedback_message = "Sucesso: E-mail de teste enviado com sucesso.";
                log_message($feedback_message);
            } else {
                $feedback_message = "Erro: Falha ao enviar o e-mail de teste. Saída: " 
                                    . htmlspecialchars($result['output']);
                log_message($feedback_message);
            }
        }
    }

    // --------------------------------------------------
    // 4. Excluir arquivo gerado (action implícita via delete_file)
    // --------------------------------------------------
    if (isset($_POST['delete_file'])) {
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

    // --------------------------------------------------
    // 5. Configurar agendamento de backup (action = 'schedule')
    // --------------------------------------------------
    if ($action === 'schedule') {
        $schedule_time = htmlspecialchars($_POST['schedule_time']);
        $minute       = htmlspecialchars($_POST['minute'] ?? "0");
        $hour         = htmlspecialchars($_POST['hour']   ?? "*");
        $day          = htmlspecialchars($_POST['day']    ?? "*");
        $month        = htmlspecialchars($_POST['month']  ?? "*");
        $weekday      = htmlspecialchars($_POST['weekday']?? "*");

        // Traduz as opções do select
        switch ($schedule_time) {
            case 'hourly':
                $minute = '0';
                $hour = '*';
                $day = '*';
                $month = '*';
                $weekday = '*';
                break;
            case 'daily':
                $day = '*';
                $month = '*';
                $weekday = '*';
                break;
            case 'weekly':
                $day = '*';
                $month = '*';
                break;
            case 'monthly':
                $month = '*';
                $weekday = '*';
                break;
        }

        // Monta a entrada de cron
        $cron_entry = [
            'minute' => $minute,
            'hour'   => $hour,
            'mday'   => $day,
            'month'  => $month,
            'wday'   => $weekday,
            'who'    => 'root',
            'command'=> $cron_command,  // /usr/local/bin/python3.11 /root/envia_email.pyc
        ];

        // Adicionar entrada ao config.xml do pfSense
        if (!is_array($config['cron']['item'])) {
            $config['cron']['item'] = [];
        }
        $config['cron']['item'][] = $cron_entry;
        write_config("Adicionado agendamento de backup por e-mail.");

        $feedback_message = "Sucesso: Agendamento de backup configurado.";
        log_message($feedback_message);
    }

    // --------------------------------------------------
    // 6. Limpar log (action = 'clear_log')
    // --------------------------------------------------
    if ($action === 'clear_log') {
        $log_file = "/var/log/scriptbackupemail.log";
        
        if (file_exists($log_file)) {
            if (file_put_contents($log_file, "") !== false) {
                $feedback_message = "Sucesso: Log limpo com sucesso.";
                log_message("Log limpo manualmente pelo usuário.");
            } else {
                $feedback_message = "Erro: Não foi possível limpar o log.";
                log_message("Erro ao tentar limpar o log.");
            }
        } else {
            $feedback_message = "Erro: Arquivo de log não encontrado.";
            log_message("Tentativa de limpar log inexistente.");
        }
    }
}

// ==================================================
// ================ INTERFACE (HTML) ================
// ==================================================
$pgtitle = array("Services", "Email Backup");
include("head.inc");
?>

<!-- Mensagem de Feedback -->
<?php if (!empty($feedback_message)): ?>
<div class="alert alert-info">
    <?= htmlspecialchars($feedback_message) ?>
</div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Configuração de Backup por E-mail</h2></div>
  <div class="panel-body">
    <form method="post">
      <input name="subject" type="text" class="form-control" placeholder="Assunto" required><br>
      <input name="receiver_email" type="email" class="form-control" placeholder="E-mail do Destinatário" required><br>
      <input name="username" type="text" class="form-control" placeholder="Usuário SMTP" required><br>
      <input name="password" type="password" class="form-control" placeholder="Senha SMTP" required><br>

      <!-- Campos adicionais para servidor/porta/criptografia -->
      <input name="smtp_host" type="text" class="form-control" placeholder="SMTP Host (ex: smtp.gmail.com)" required><br>
      <input name="smtp_port" type="number" class="form-control" placeholder="SMTP Port (ex: 587)" required><br>

      <label for="smtp_encryption">Criptografia:</label>
      <select name="smtp_encryption" class="form-control">
          <option value="none">Nenhuma</option>
          <option value="ssl">SSL/TLS</option>
          <option value="starttls">STARTTLS</option>
      </select>
      <small class="form-text text-muted">
          <strong>Nota:</strong> Para Gmail, use STARTTLS (porta 587) ou SSL (porta 465).  
          Para Outlook/Office 365, geralmente STARTTLS (porta 587).  
          Para servidores internos, por vezes (porta 25) sem criptografia.  
      </small>
      <br>

      <button type="submit" name="action" value="process" class="btn btn-primary">Criar Arquivo</button>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Validar Config.XML</h2></div>
  <div class="panel-body">
    <form method="post">
      <button type="submit" name="action" value="validate" class="btn btn-warning">Validar Config.XML</button>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Testar Envio de Backup por E-mail</h2></div>
  <div class="panel-body">
    <form method="post">
      <button type="submit" name="action" value="test_email" class="btn btn-info">Testar Envio de Backup</button>
    </form>
  </div>
</div>

<!-- Seção para exibir arquivos .pyc gerados -->
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

<!-- Agendamento de Backup -->
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
          <label for="minute">Minuto (0-59):</label>
          <input name="minute" type="number" class="form-control" placeholder="0-59">
        </div>
        <div class="form-group">
          <label for="hour">Hora (0-23):</label>
          <input name="hour" type="number" class="form-control" placeholder="0-23">
        </div>
        <div class="form-group" id="day_option" style="display:none;">
          <label for="day">Dia (1-31):</label>
          <input name="day" type="number" class="form-control" placeholder="1-31">
        </div>
        <div class="form-group" id="weekday_option" style="display:none;">
          <label for="weekday">Dia da Semana (0=domingo, 6=sábado):</label>
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
    // Exibe ou esconde inputs dependendo do valor selecionado
    document.getElementById('day_option').style.display = (value === 'daily' || value === 'monthly') ? 'block' : 'none';
    document.getElementById('weekday_option').style.display = (value === 'weekly') ? 'block' : 'none';
}
</script>

<!-- Estatísticas de uso -->
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

<!-- Pré-visualização do Log -->
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

<!-- Limpar Log -->
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Gerenciamento de Log</h2></div>
  <div class="panel-body">
    <form method="post" onsubmit="return confirm('Tem certeza que deseja limpar o log? Esta ação não pode ser desfeita.') && refreshPage()">
      <button type="submit" name="action" value="clear_log" class="btn btn-danger">Limpar Log</button>
    </form>
  </div>
</div>

<script>
function refreshPage() {
    setTimeout(() => {
        window.location.reload();
    }, 500);
    return true; 
}
</script>

<?php
include("foot.inc");
?>

#!/bin/sh
#
# install_email_backup.sh
# Exemplo de script para inserir a entrada do "Email backup" no menu Services
# após a linha que contém "DHCPv6 Relay", e então baixar o arquivo
# services_emailbackup.php do seu repositório GitHub.

HEAD_INC="/usr/local/www/head.inc"
EMAIL_BACKUP_LINE='$services_menu[] = array(gettext("Email backup"), "/services_emailbackup.php");'

echo "==> Iniciando instalação do Email Backup no pfSense..."

# 1) Verifica se a linha de Email backup já existe em head.inc
if grep -qF "$EMAIL_BACKUP_LINE" "$HEAD_INC"; then
    echo "A linha de Email backup já existe em $HEAD_INC. Não será duplicada."
else
    echo "Inserindo a linha de Email backup no arquivo head.inc..."

    # 1.1) Insere a linha APÓS qualquer linha que contenha "DHCPv6 Relay"
    #      Dessa forma, não precisamos casar a linha exata, só a substring "DHCPv6 Relay"
    sed -i '' "/DHCPv6 Relay/a\\
$EMAIL_BACKUP_LINE
" "$HEAD_INC"

    echo "Linha inserida com sucesso no menu Services."
fi

# 2) Baixar o arquivo services_emailbackup.php do GitHub
echo "Baixando o arquivo services_emailbackup.php do GitHub..."
fetch -o /usr/local/www/services_emailbackup.php \
  https://raw.githubusercontent.com/pablomichelin/pfsense/refs/heads/main/services_emailbackup.php%20(others)

# 3) Ajustar permissões
chmod 644 /usr/local/www/services_emailbackup.php

# 4) Mensagem final
echo "Instalação concluída!"
echo "Acesse 'Services > Email backup' na GUI do pfSense para usar o script."

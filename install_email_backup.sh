#!/bin/sh
#
# install_email_backup.sh
# Exemplo de script para inserir a entrada do "Email backup" no menu Services
# e baixar o arquivo services_emailbackup.php no pfSense.

HEAD_INC="/usr/local/www/head.inc"
LINE_EMAIL_BACKUP='$services_menu[] = array(gettext("Email backup"), "/services_emailbackup.php");'
LINE_RELAY_MATCH='$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");'

echo "==> Iniciando instalação do Email Backup no pfSense..."

# 1) Verifica se a linha de Email backup já existe em head.inc
if grep -qF "$LINE_EMAIL_BACKUP" "$HEAD_INC"; then
    echo "A linha de Email backup já existe em $HEAD_INC. Não será duplicada."
else
    echo "Inserindo a linha de Email backup no arquivo head.inc..."

    # 1.1) Insere a linha após o DHCPv6 Relay
    #      Observação: '-i ''' é para editar "in-place" sem criar backup no FreeBSD/pfSense.
    sed -i '' "/$LINE_RELAY_MATCH/a\\
$LINE_EMAIL_BACKUP
" "$HEAD_INC"

    echo "Linha inserida com sucesso no menu Services."
fi

# 2) Baixar o arquivo services_emailbackup.php
echo "Baixando o arquivo services_emailbackup.php do GitHub..."
fetch -o /usr/local/www/services_emailbackup.php \
  https://raw.githubusercontent.com/pablomichelin/pfsense/main/services_emailbackup.php

# 3) Ajustar permissões
chmod 644 /usr/local/www/services_emailbackup.php

# 4) Mensagem final
echo "Instalação concluída!"
echo "Acesse 'Services > Email backup' na GUI do pfSense para usar o script."

local-exam_remote
=================

Plugin local que fornece os webservices necessários no "Moodle de origem" para realização de provas.

Serve para criar os webservices necessários para importação
dos usuários, disponibilização de provas e cópia das provas
do ambiente de provas para o Moodle de origem.


Moodle Provas
=============

O "Moodle Provas" é uma solução desenvolvida pela
Universidade Federal de Santa Catarina
com financiamenteo do programa Universidade Aberta do Brasil (UAB)
para a realização de provas seguras nos pólos utilizando
o Moodle através da internet.

Além deste plugin, mais dois plugins compõem o pacote do Moodle Provas:

* block-exam_actions : Bloco que serve de interface para as ações sobre as provas
* local-exam_authorization : Bloco que trata da autorização de usuários ao ambiente de provas

Foi desenvolvido também um "CD de Provas", derivado do Ubuntu, para
restringir o acesso aos recursos dos computadores utilizados
para realização da provas.

No endereço abaixo você pode acessar um tutorial sobre a
arquitetura do Moodle Provas:

    https://github.com/UFSC/moodle-provas-blocks-exam_actions/wikis/home

Download
========

Este plugin está disponível no seguinte endereço:

    https://github.com/UFSC/moodle-provas-local-exam_remote

Os outros plugins podem ser encontrados em:

    https://github.com/UFSC/moodle-provas-local-exam_authorization
    https://github.com/UFSC/moodle-provas-blocks-exam_actions

O código e instruções para gravação do "CD de Provas" podem ser encontrados em:

    https://github.com/UFSC/moodle-provas-livecd-provas

Instalação
==========

* Este plugin deve ser instalado no "Moodle de origem" (um ou mais).
* Este plugin é do tipo local, logo deve ser instalado no diretório "local", na raiz do seu moodle.
* O nome diretório deste plugin dentro do diretório "local" deve ser "exam_remote" (sem as aspas).
* Após colocar o código do plugin no diretório correto, visite o seu Moodle como administrador para finalizar a instalação.

Pós-instalação
==============

Há um script em cli/configure_remote.php que realizar diversas operações de configuração, dentre elas:

* Cria um usuário para o webservice
* Cria um papel para o webservice
* Atribui papel ao usuário no contexto global
* Habilita o uso de webservices
* Ativa protocolo (REST, por padrão)
* Adiciona usuário ao serviço
* Gera token

Para executar este script través da linha de comando, você vai precisar do "php-cli" (php command line interface).
Com o php-cli instalado, você pode acessar o diretório do plugin e executar o script com os seguintes comandos:

    cd moodle/local/exam_remote
    php cli/configure_remote.php


Licença
=======

Este código-fonte é distribuído sob licença GNU General Plublic License
Uma cópia desta licença está no arquivo COPYING.txt
Ela também pode ser vista em <http://www.gnu.org/licenses/>.

# KOBOSender — Envio de Ficheiros EPUB para Kobo

Sistema leve em PHP puro para enviar ficheiros EPUB para um leitor Kobo através de uma chave de acesso de 4 caracteres. Sem dependências externas, sem base de dados, sem frameworks.

## Especificações técnicas

| Item | Valor |
|------|-------|
| Linguagem | PHP 7.4+ (`declare(strict_types=1)`) |
| Servidor web | Apache com mod_rewrite |
| Base de dados | Nenhuma (estado em ficheiro JSON) |
| Ficheiros de estado | `current.json` (sessões e ficheiros) |
| Ficheiros enviados | Pasta `uploads/` |
| Tipo de ficheiro permitido | `.epub` |
| Tamanho máximo por ficheiro | 30 MB |
| Expiração de sessão/ficheiro | 3600 segundos (1 hora) |
| Chave de acesso | 4 caracteres alfanuméricos (sem vogais ambíguas) |
| Frontend | HTML5 + CSS3 + JavaScript vanilla (progresso de upload via XHR) |

## Estrutura do repositório

```
KOBOSender/
├── index.php         # Gera/exibe chave de 4 caracteres; redireciona para /files se houver ficheiros
├── upload.php        # Recebe upload multipart de ficheiros .epub associados a uma chave
├── send.php          # Interface para definir chave e enviar ficheiros EPUB (com barra de progresso)
├── files.php         # Lista ficheiros disponíveis para download por chave; permite download/eliminar/limpar
├── style.css         # Estilos mínimos
├── .htaccess         # Regras Apache: DirectoryIndex, reescrita /files e /send, cache de CSS
├── current.json      # Estado de sessões e ficheiros (runtime, não versionado)
├── uploads/          # Ficheiros EPUB enviados (runtime, não versionado)
├── .gitignore
└── README.md
```

## Funcionamento

1. O utilizador acede a `/` e recebe uma chave de 4 caracteres (ex.: `C6NK`), armazenada num cookie `kobo_code`.
2. No dispositivo Kobo (ou noutro browser), acede a `/send`, insere a chave e envia um ou mais ficheiros `.epub` (até 30 MB cada).
3. Os ficheiros são guardados em `uploads/` com nomes aleatórios e associados à chave em `current.json`.
4. O utilizador acede a `/files?c=C6NK` para listar, descarregar ou eliminar os ficheiros.
5. Sessões e ficheiros expiram após 1 hora de inatividade e são removidos automaticamente (incluindo os ficheiros físicos de `uploads/`).

## Endpoints

| Caminho | Método | Descrição |
|---------|--------|-----------|
| `/` | GET | Exibe a chave de sessão atual |
| `/` | POST (`new_key`) | Gera uma nova chave |
| `/send` | GET | Formulário para definir chave e enviar ficheiros |
| `/send` | POST (`set_code`) | Associa o browser a uma chave existente |
| `/upload.php` | POST (`multipart`) | Upload de ficheiros `.epub` para uma chave |
| `/files?c=CHAVE` | GET | Lista ficheiros válidos da chave |
| `/files?c=CHAVE` | POST (`i`) | Descarregar ficheiro pelo índice |
| `/files?c=CHAVE` | POST (`del`) | Eliminar ficheiro pelo índice |
| `/files?c=CHAVE` | POST (`clear`) | Eliminar todos os ficheiros da chave |

## Variáveis de ambiente / Configuração

Não há ficheiro `.env`. As constantes estão definidas no topo de cada ficheiro PHP:

- `EXPIRE_SECONDS` — tempo de vida de sessões e ficheiros (padrão: 3600 s)
- `MAX_FILE_SIZE` — tamanho máximo de upload (padrão: 30 MB)
- `DATA_FILE` — caminho para `current.json`
- `UPLOAD_DIR` — caminho para pasta de ficheiros enviados
- `allowedExtensions` — em `upload.php`, atualmente apenas `['epub']`

## Instalação

1. Copie a pasta `KOBOSender` para o diretório de documentos do servidor web (ex.: `C:\laragon\www\KOBOSender`).
2. Inicie Apache (Laragon/XAMPP ou equivalente) com `mod_rewrite` ativado.
3. Garanta que o utilizador do servidor web tem escrita em:
   - `current.json` (será criado se não existir)
   - `uploads/` (será criada se não existir)
4. Aceda a `http://localhost/KOBOSender`.

## Requisitos do servidor

- PHP 7.4 ou superior
- Apache com `mod_rewrite` (para URLs `/files` e `/send`)
- Extensão `fileinfo` não é necessária (validação por extensão de ficheiro)
- Funções `random_bytes`, `flock`, `file_get_contents`, `move_uploaded_file`

## Notas de segurança

- A pasta `uploads/` e o ficheiro `current.json` contêm dados de utilizador e estado de sessão; estão no `.gitignore` e não devem ser versionados.
- Não há autenticação de utilizador; a segurança baseia-se na chave de 4 caracteres (espaço reduzido, adequado para uso temporário e local).
- Os ficheiros são removidos após 1 hora ou ao serem descarregados/eliminados.
- O upload está limitado a ficheiros `.epub` e 30 MB.

## Licença

© 2026 João Pereira — https://github.com/Joners1108

Este projeto está disponível para uso pessoal e académico. Para uso comercial ou redistribuição, contacte o autor através do GitHub.

O código é fornecido "tal como está", sem garantias de qualquer tipo.

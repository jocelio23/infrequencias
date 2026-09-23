Instruções de funcionamento para colocar as infrequências quando o sistema desligar

criar aquivo. bat -> inicializar repositório com as config git-> agendar tarefa no windows (win+R taskschd.msc)

@echo off

REM ============================================================
REM CONFIGURACAO
REM ============================================================

set PROJETO=C:\laragon\www\infrequencias
set REPOSITORIO=%USERPROFILE%\Desktop\chamadas
set JSON_ORIGEM=%PROJETO%\dados\infrequencias.json
set JSON_GITHUB=%REPOSITORIO%\dados\infrequencias.json
set LOG=%PROJETO%\logs\execucao.log


REM ============================================================
REM INICIO
REM ============================================================

echo. >> "%LOG%"
echo ============================================================ >> "%LOG%"
echo INICIO: %date% %time% >> "%LOG%"
echo ============================================================ >> "%LOG%"

echo.
echo ========================================
echo  EXTRATOR DE INFREQUENCIAS
echo ========================================
echo.
echo Iniciando extracao...


REM ============================================================
REM EXECUTA O PYTHON
REM ============================================================

cd /d "%PROJETO%"

python extrator.py >> "%LOG%" 2>&1

if not exist "%JSON_ORIGEM%" (
    echo ERRO: arquivo JSON nao foi gerado.
    echo ERRO: arquivo JSON nao foi gerado. >> "%LOG%"
    echo FIM: %date% %time% >> "%LOG%"
    exit /b 1
)

echo Extracao concluida.
echo Arquivo JSON encontrado.


REM ============================================================
REM COPIA O JSON PARA O REPOSITORIO GITHUB
REM ============================================================

echo.
echo Copiando JSON para o repositorio...

if not exist "%REPOSITORIO%\dados" (
    mkdir "%REPOSITORIO%\dados"
)

copy /Y "%JSON_ORIGEM%" "%JSON_GITHUB%" > nul

if errorlevel 1 (
    echo ERRO ao copiar o arquivo JSON.
    echo ERRO ao copiar o arquivo JSON. >> "%LOG%"
    exit /b 1
)

echo JSON copiado com sucesso.
echo JSON copiado para o repositorio. >> "%LOG%"


REM ============================================================
REM ENTRA NO REPOSITORIO
REM ============================================================

cd /d "%REPOSITORIO%"

echo.
echo Verificando alteracoes do Git...


REM ============================================================
REM ADICIONA O JSON
REM ============================================================

git add dados\infrequencias.json >> "%LOG%" 2>&1


REM ============================================================
REM VERIFICA SE HOUVE ALTERACAO
REM ============================================================

git diff --cached --quiet

if %errorlevel%==0 (
    echo.
    echo Nenhuma alteracao no arquivo.
    echo Nenhuma alteracao para enviar ao GitHub. >> "%LOG%"
    goto FINAL
)


REM ============================================================
REM COMMIT
REM ============================================================

echo.
echo Criando commit...

git commit -m "Atualiza dados de infrequencias" >> "%LOG%" 2>&1

if errorlevel 1 (
    echo ERRO ao criar o commit.
    echo ERRO ao criar o commit. >> "%LOG%"
    exit /b 1
)

echo Commit criado com sucesso.


REM ============================================================
REM ENVIA PARA O GITHUB
REM ============================================================

echo.
echo Enviando dados para o GitHub...
echo.

git push origin main >> "%LOG%" 2>&1

if errorlevel 1 (
    echo.
    echo ERRO ao enviar para o GitHub.
    echo ERRO ao enviar para o GitHub. >> "%LOG%"
    exit /b 1
)

echo.
echo ========================================
echo  ENVIO CONCLUIDO COM SUCESSO!
echo ========================================
echo.

echo GitHub atualizado com sucesso. >> "%LOG%"


REM ============================================================
REM FINAL
REM ============================================================

:FINAL

echo.
echo FIM: %date% %time% >> "%LOG%"
echo ============================================================ >> "%LOG%"
echo. >> "%LOG%"

exit /b 0

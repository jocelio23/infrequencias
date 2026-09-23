
import mysql.connector
import json
import os
from datetime import date, datetime


# ============================================================
# CONFIGURAÇÕES DO BANCO DE DADOS
# ============================================================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "escola",
}


# ============================================================
# CONFIGURAÇÃO DAS PASTAS
# ============================================================

# Pega a pasta onde este arquivo extrator.py está localizado
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# Pasta onde será salvo o JSON
PASTA_SAIDA = os.path.join(
    BASE_DIR,
    "dados"
)

# Pasta onde serão armazenados os logs
PASTA_LOGS = os.path.join(
    BASE_DIR,
    "logs"
)

# Cria as pastas caso ainda não existam
os.makedirs(PASTA_SAIDA, exist_ok=True)
os.makedirs(PASTA_LOGS, exist_ok=True)


# ============================================================
# ARQUIVOS
# ============================================================

DATA_ATUAL = datetime.now().strftime("%Y-%m-%d")

ARQUIVO_JSON = os.path.join(
    PASTA_SAIDA,
    "infrequencias.json"
)

ARQUIVO_LOG = os.path.join(
    PASTA_LOGS,
    "extrator.log"
)


# ============================================================
# FUNÇÃO DE LOG
# ============================================================

def registrar_log(mensagem):
    """
    Registra uma mensagem no arquivo de log.
    """

    data_hora = datetime.now().strftime(
        "%Y-%m-%d %H:%M:%S"
    )

    linha = f"[{data_hora}] {mensagem}\n"

    with open(
        ARQUIVO_LOG,
        "a",
        encoding="utf-8"
    ) as arquivo:

        arquivo.write(linha)


# ============================================================
# CONVERSÃO PARA JSON
# ============================================================

def converter_json(obj):
    """
    Converte objetos date/datetime para texto.
    """

    if isinstance(obj, (date, datetime)):
        return obj.isoformat()

    raise TypeError(
        f"Tipo não serializável: {type(obj)}"
    )


# ============================================================
# EXTRAÇÃO DAS INFREQUÊNCIAS
# ============================================================

def extrair_infrequencias():

    conexao = None
    cursor = None

    try:

        print("=" * 60)
        print("EXTRAÇÃO DE INFREQUÊNCIAS")
        print("=" * 60)

        print(
            f"Data da extração: {DATA_ATUAL}"
        )

        registrar_log("=" * 60)
        registrar_log("INÍCIO DA EXTRAÇÃO")
        registrar_log(
            f"Data da extração: {DATA_ATUAL}"
        )

        print(
            "Conectando ao banco de dados..."
        )

        registrar_log(
            "Conectando ao banco de dados..."
        )

        # Conexão com o MySQL
        conexao = mysql.connector.connect(
            **DB_CONFIG
        )

        print(
            "Banco de dados conectado com sucesso!"
        )

        registrar_log(
            "Banco de dados conectado com sucesso!"
        )


        # ====================================================
        # CURSOR
        # ====================================================

        cursor = conexao.cursor(
            dictionary=True
        )


        # ====================================================
        # CONSULTA SQL
        # ====================================================

        sql = """
            SELECT
                c.id AS chamada_id,
                c.data,
                c.status,
                c.created_at,

                a.id AS aluno_id,
                a.nome AS aluno_nome,
                a.turma_id,

                t.nome AS turma_nome,
                t.ano,

                cu.id AS curso_id,
                cu.nome AS curso_nome,

                c.professor_id

            FROM chamadas c

            INNER JOIN alunos a
                ON a.id = c.aluno_id

            INNER JOIN turmas t
                ON t.id = a.turma_id

            INNER JOIN cursos cu
                ON cu.id = t.curso_id

            WHERE LOWER(TRIM(c.status)) = 'falta'

            ORDER BY
                t.nome ASC,
                a.nome ASC,
                c.data ASC,
                c.id ASC
        """


        # ====================================================
        # EXECUTA CONSULTA
        # ====================================================

        print(
            "Executando consulta..."
        )

        registrar_log(
            "Executando consulta..."
        )

        cursor.execute(sql)

        registros = cursor.fetchall()


        # ====================================================
        # TOTAL
        # ====================================================

        total_faltas = len(registros)

        print(
            f"Total de faltas encontradas: {total_faltas}"
        )

        registrar_log(
            f"Total de faltas encontradas: {total_faltas}"
        )


        # ====================================================
        # MONTA JSON
        # ====================================================

        resultado = {

            "sistema": "Escola",

            "tipo": "Infrequências",

            "data_extracao": DATA_ATUAL,

            "data_hora_extracao":
                datetime.now().isoformat(),

            "total_faltas":
                total_faltas,

            "infrequencias":
                registros
        }


        # ====================================================
        # SALVA JSON
        # ====================================================

        print(
            "Salvando arquivo JSON..."
        )

        registrar_log(
            "Salvando arquivo JSON..."
        )

        with open(
            ARQUIVO_JSON,
            "w",
            encoding="utf-8"
        ) as arquivo:

            json.dump(
                resultado,
                arquivo,
                ensure_ascii=False,
                indent=4,
                default=converter_json
            )


        # ====================================================
        # SUCESSO
        # ====================================================

        caminho_json = os.path.abspath(
            ARQUIVO_JSON
        )

        print()
        print("SUCESSO!")

        print(
            f"Arquivo gerado: {caminho_json}"
        )

        print(
            f"Total de registros: {total_faltas}"
        )

        print("=" * 60)

        registrar_log(
            "EXTRAÇÃO CONCLUÍDA COM SUCESSO"
        )

        registrar_log(
            f"Arquivo gerado: {caminho_json}"
        )

        registrar_log(
            f"Total de registros: {total_faltas}"
        )

        registrar_log("=" * 60)


    # ========================================================
    # ERRO DO MYSQL
    # ========================================================

    except mysql.connector.Error as erro:

        print()
        print("ERRO NO MYSQL")
        print(erro)
        print("=" * 60)

        registrar_log(
            "ERRO NO MYSQL"
        )

        registrar_log(
            str(erro)
        )


    # ========================================================
    # OUTROS ERROS
    # ========================================================

    except Exception as erro:

        print()
        print("ERRO DURANTE A EXECUÇÃO")
        print(erro)
        print("=" * 60)

        registrar_log(
            "ERRO DURANTE A EXECUÇÃO"
        )

        registrar_log(
            str(erro)
        )


    # ========================================================
    # FECHAMENTO
    # ========================================================

    finally:

        if cursor is not None:

            cursor.close()


        if (
            conexao is not None
            and conexao.is_connected()
        ):

            conexao.close()

            print(
                "Conexão com o banco encerrada."
            )

            registrar_log(
                "Conexão com o banco encerrada."
            )


# ============================================================
# EXECUÇÃO PRINCIPAL
# ============================================================

if __name__ == "__main__":

    extrair_infrequencias()


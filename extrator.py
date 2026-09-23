import mysql.connector
import json
import os
from datetime import date, datetime


# ============================================================
# CONFIGURAÇÕES DO BANCO
# ============================================================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "escola",
}


# ============================================================
# CONFIGURAÇÃO DOS ARQUIVOS
# ============================================================

PASTA_SAIDA = "dados"

DATA_ATUAL = datetime.now().strftime("%Y-%m-%d")

ARQUIVO_JSON = os.path.join(
    PASTA_SAIDA,
    f"infrequencias.json"
)


# ============================================================
# CRIAR PASTA
# ============================================================

os.makedirs(PASTA_SAIDA, exist_ok=True)


# ============================================================
# CONVERTER DATA/HORA PARA JSON
# ============================================================

def converter_json(obj):

    # Trata tanto DATE quanto DATETIME do MySQL
    if isinstance(obj, (date, datetime)):
        return obj.isoformat()

    raise TypeError(
        f"Tipo não serializável: {type(obj)}"
    )


# ============================================================
# EXTRAIR INFREQUÊNCIAS
# ============================================================

def extrair_infrequencias():

    conexao = None
    cursor = None

    try:

        print("=" * 60)
        print("EXTRAÇÃO DE INFREQUÊNCIAS")
        print("=" * 60)

        print(f"Data da extração: {DATA_ATUAL}")
        print("Conectando ao banco de dados...")

        # ----------------------------------------------------
        # CONEXÃO
        # ----------------------------------------------------

        conexao = mysql.connector.connect(
            **DB_CONFIG
        )

        print("Banco de dados conectado com sucesso!")

        cursor = conexao.cursor(
            dictionary=True
        )

        # ----------------------------------------------------
        # CONSULTA
        # ----------------------------------------------------

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

        print("Executando consulta...")

        cursor.execute(sql)

        registros = cursor.fetchall()

        print(
            f"Total de faltas encontradas: {len(registros)}"
        )

        # ----------------------------------------------------
        # RESULTADO
        # ----------------------------------------------------

        resultado = {

            "sistema": "Escola",

            "tipo": "Infrequências",

            "data_extracao": DATA_ATUAL,

            "data_hora_extracao": datetime.now().isoformat(),

            "total_faltas": len(registros),

            "infrequencias": registros
        }

        # ----------------------------------------------------
        # SALVAR JSON
        # ----------------------------------------------------

        print("Salvando arquivo JSON...")

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

        # ----------------------------------------------------
        # FINAL
        # ----------------------------------------------------

        print()
        print("SUCESSO!")
        print(
            f"Arquivo gerado: {os.path.abspath(ARQUIVO_JSON)}"
        )

        print(
            f"Total de registros: {len(registros)}"
        )

        print("=" * 60)

    except mysql.connector.Error as erro:

        print()
        print("ERRO NO MYSQL")
        print(erro)
        print("=" * 60)

    except Exception as erro:

        print()
        print("ERRO DURANTE A EXECUÇÃO")
        print(erro)
        print("=" * 60)

    finally:

        # ----------------------------------------------------
        # FECHAR CURSOR
        # ----------------------------------------------------

        if cursor is not None:
            cursor.close()

        # ----------------------------------------------------
        # FECHAR CONEXÃO
        # ----------------------------------------------------

        if (
            conexao is not None
            and conexao.is_connected()
        ):

            conexao.close()

            print(
                "Conexão com o banco encerrada."
            )


# ============================================================
# EXECUTAR
# ============================================================

if __name__ == "__main__":

    extrair_infrequencias()

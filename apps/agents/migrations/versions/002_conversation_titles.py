import sqlalchemy as sa
from alembic import op

revision = "002_conversation_titles"
down_revision = "001_conversations"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column("agent_threads", sa.Column("title", sa.String(80), nullable=True))
    op.add_column(
        "agent_threads",
        sa.Column("title_references", sa.JSON(), nullable=False, server_default=sa.text("'[]'")),
    )


def downgrade() -> None:
    op.drop_column("agent_threads", "title_references")
    op.drop_column("agent_threads", "title")

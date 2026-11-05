import sqlalchemy as sa
from alembic import op

revision = "003_conversation_context"
down_revision = "002_conversation_titles"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column("agent_threads", sa.Column("conversation_context", sa.JSON(), nullable=True))


def downgrade() -> None:
    op.drop_column("agent_threads", "conversation_context")

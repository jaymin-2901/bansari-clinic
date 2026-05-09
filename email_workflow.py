# Multi-Agent Workflow: Conditional Email Spam Detection

from agent_framework import WorkflowBuilder, handler, WorkflowContext, AgentRunUpdateEvent, AgentRunResponseUpdate, TextContent, Role
from uuid import uuid4

# --- Agent Definitions ---

class DetectionResult:
    def __init__(self, is_spam: bool, reason: str):
        self.is_spam = is_spam
        self.reason = reason

class SpamDetectionAgent:
    @handler
    async def detect(self, email: str, ctx: WorkflowContext[DetectionResult]) -> DetectionResult:
        # Minimal spam detection logic
        if "buy now" in email.lower():
            return DetectionResult(is_spam=True, reason="Contains spam phrase")
        return DetectionResult(is_spam=False, reason="No spam detected")

class EmailAssistantAgent:
    @handler
    async def draft_reply(self, detection: DetectionResult, ctx: WorkflowContext[str]) -> str:
        # Transform detection output into user message
        return f"Thank you for your email. We have reviewed it: {detection.reason}"

class SpamHandlerAgent:
    @handler
    async def handle_spam(self, detection: DetectionResult, ctx: WorkflowContext[str]) -> str:
        return "This email was classified as spam. No reply will be sent."

# --- Workflow Definition ---

def build_workflow():
    builder = WorkflowBuilder()
    builder.add_edge("start", SpamDetectionAgent(), "detect")
    builder.add_edge("not_spam", EmailAssistantAgent(), "draft_reply")
    builder.add_edge("spam", SpamHandlerAgent(), "handle_spam")

    @handler
    async def starter(self, email: str, ctx: WorkflowContext[str]):
        detection = await ctx.run("start", email)
        if detection.is_spam:
            result = await ctx.run("spam", detection)
        else:
            result = await ctx.run("not_spam", detection)
        await ctx.add_event(
            AgentRunUpdateEvent(
                self.id,
                data=AgentRunResponseUpdate(
                    contents=[TextContent(text=result)],
                    role=Role.ASSISTANT,
                    response_id=str(uuid4()),
                ),
            )
        )
        return result

    builder.set_start_executor(starter)
    return builder.build().as_agent()

# --- HTTP Server Entrypoint ---

if __name__ == "__main__":
    from azure.ai.agentserver.agentframework import from_agent_framework
    import asyncio
    agent = build_workflow()
    asyncio.run(from_agent_framework(agent).run_async())

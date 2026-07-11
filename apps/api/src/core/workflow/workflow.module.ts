import { Module } from "@nestjs/common";
import { WorkflowEngineService } from "./workflow-engine.service";
import { NodeExecutorRegistry } from "./node-executor.registry";

/**
 * Workflow Engine (ADR-006): motor visual de fluxos por empresa.
 * Nada de fluxos fixos — cada tenant desenha os seus.
 */
@Module({
  providers: [WorkflowEngineService, NodeExecutorRegistry],
  exports: [WorkflowEngineService],
})
export class WorkflowModule {}

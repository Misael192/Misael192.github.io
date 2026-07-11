import { Global, Module } from "@nestjs/common";
import { FeatureFlagsService } from "./feature-flags.service";
import { ModuleGuard } from "./module.guard";

@Global()
@Module({
  providers: [FeatureFlagsService, ModuleGuard],
  exports: [FeatureFlagsService, ModuleGuard],
})
export class FeatureFlagsModule {}

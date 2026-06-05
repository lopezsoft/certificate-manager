import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

import { CoreDirectivesModule } from '@core/directives/directives';
import { CorePipesModule } from '@core/pipes/pipes.module';

@NgModule({
  imports: [CommonModule, FormsModule, ReactiveFormsModule, CoreDirectivesModule, CorePipesModule],
  exports: [CommonModule, FormsModule, ReactiveFormsModule, CoreDirectivesModule, CorePipesModule]
})
export class CoreCommonModule {}


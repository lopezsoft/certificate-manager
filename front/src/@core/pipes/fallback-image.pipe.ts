import { Pipe, PipeTransform, inject } from '@angular/core';
import { ImageValidatorService } from '@core/services/image-validator.service';

@Pipe({
  name: 'fallbackImage',
  standalone: false
})
export class FallbackImagePipe implements PipeTransform {
  private imageValidator = inject(ImageValidatorService);

  transform(imagePath: string, fallback: string = 'assets/flags/empty-flag.png'): string {
    if (!imagePath || typeof imagePath !== 'string') {
      return fallback;
    }

    let resolvedPath = imagePath.trim();

    if (!resolvedPath || resolvedPath === '' || resolvedPath === '.png') {
      return fallback;
    }

    if (!resolvedPath.match(/\.(png|jpg|jpeg|gif)$/i)) {
      resolvedPath = resolvedPath.toLowerCase() + '.png';
    }

    const isValid = this.imageValidator.isImageValid(resolvedPath);

    if (!isValid) {
      return fallback;
    }

    this.imageValidator.validateImage(resolvedPath);
    return resolvedPath;
  }
}

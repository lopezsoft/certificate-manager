import { Pipe, PipeTransform } from '@angular/core';

/**
 * AvatarFallbackPipe — Retorna una imagen de avatar de fallback
 * cuando el avatar del usuario no existe, está vacío o es inválido.
 *
 * Uso: [src]="user.avatar | avatarFallback:user.firstName"
 *
 * Las imágenes de fallback se toman de assets/avatars/:
 *   man.png     → para nombres que inician con A-M (o sin nombre)
 *   woman.png   → para nombres que inician con N-Z
 *   unknown.png → cuando no hay información de nombre
 */
@Pipe({
  name: 'avatarFallback',
  pure: true,
  standalone: false,
})
export class AvatarFallbackPipe implements PipeTransform {

  private static readonly FALLBACKS = [
    'assets/avatars/man.png',
    'assets/avatars/woman.png',
    'assets/avatars/unknown.png',
  ];

  private static readonly INVALID_PATTERNS = [
    null,
    undefined,
    '',
    'undefined',
    'null',
    'no-image',
  ];

  transform(avatar: string | null | undefined, firstName?: string): string {
    // Si el avatar es válido, lo retorna directamente
    if (
      avatar &&
      !AvatarFallbackPipe.INVALID_PATTERNS.includes(avatar as any) &&
      !avatar.includes('no-image') &&
      !avatar.includes('undefined') &&
      !avatar.includes('null')
    ) {
      return avatar;
    }

    // Sin nombre → unknown
    if (!firstName) {
      return AvatarFallbackPipe.FALLBACKS[2];
    }

    // Determina fallback por primera letra del nombre
    const firstChar = firstName.trim().toUpperCase().charCodeAt(0);
    if (isNaN(firstChar)) {
      return AvatarFallbackPipe.FALLBACKS[2];
    }

    // A–M → man, N–Z → woman
    if (firstChar >= 65 && firstChar <= 77) {
      return AvatarFallbackPipe.FALLBACKS[0]; // man
    } else if (firstChar >= 78 && firstChar <= 90) {
      return AvatarFallbackPipe.FALLBACKS[1]; // woman
    }

    return AvatarFallbackPipe.FALLBACKS[2]; // unknown
  }
}

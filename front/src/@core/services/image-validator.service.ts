import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class ImageValidatorService {
  private imageCache = new Map<string, boolean>();
  private loadingPromises = new Map<string, Promise<boolean>>();

  validateImage(url: string): Promise<boolean> {
    if (this.imageCache.has(url)) {
      return Promise.resolve(this.imageCache.get(url)!);
    }

    if (this.loadingPromises.has(url)) {
      return this.loadingPromises.get(url)!;
    }

    const promise = new Promise<boolean>((resolve) => {
      const img = new Image();
      img.onload = () => {
        this.imageCache.set(url, true);
        this.loadingPromises.delete(url);
        resolve(true);
      };
      img.onerror = () => {
        this.imageCache.set(url, false);
        this.loadingPromises.delete(url);
        resolve(false);
      };
      img.src = url;
    });

    this.loadingPromises.set(url, promise);
    return promise;
  }

  isImageValid(url: string): boolean {
    return this.imageCache.get(url) ?? true;
  }
}

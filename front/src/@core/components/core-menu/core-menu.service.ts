import { Injectable } from '@angular/core';

import { BehaviorSubject, Observable, Subject } from 'rxjs';

import { User } from 'app/auth/models';
import TokenService from "../../../app/utils/token.service";

@Injectable({
  providedIn: 'root'
})
export class CoreMenuService {
  currentUser: User;
  onItemCollapsed: Subject<any>;
  onItemCollapseToggled: Subject<any>;

  // Private
  private _onMenuRegistered: BehaviorSubject<any>;
  private _onMenuUnregistered: BehaviorSubject<any>;
  private _onMenuChanged: BehaviorSubject<any>;
  private _currentMenuKey: string;
  private _registry: { [key: string]: any } = {};
  
  constructor(
    private _authenticationService: TokenService,
    ) {

    const ts  = this;
    if(this._authenticationService.isAuthenticated()) {
      ts.currentUser  = ts._authenticationService.getCurrentUser();
    }
    // Set defaults
    ts.onItemCollapsed = new Subject();
    ts.onItemCollapseToggled = new Subject();

    // Set private defaults
    ts._currentMenuKey      = null;
    ts._onMenuRegistered    = new BehaviorSubject(null);
    ts._onMenuUnregistered  = new BehaviorSubject(null);
    ts._onMenuChanged       = new BehaviorSubject(null);
  }

  // Accessors
  // -----------------------------------------------------------------------------------------------------

  /**
   * onMenuRegistered
   *
   * @returns {Observable<any>}
   */
  get onMenuRegistered(): Observable<any> {
    return this._onMenuRegistered.asObservable();
  }

  /**
   * onMenuUnregistered
   *
   * @returns {Observable<any>}
   */
  get onMenuUnregistered(): Observable<any> {
    return this._onMenuUnregistered.asObservable();
  }

  /**
   * onMenuChanged
   *
   * @returns {Observable<any>}
   */
  get onMenuChanged(): Observable<any> {
    return this._onMenuChanged.asObservable();
  }

  // Public methods
  // -----------------------------------------------------------------------------------------------------

  /**
   * Register the provided menu with the provided key
   *
   * @param key
   * @param menu
   */
  register(key, menu): void {
    // Confirm if the key already used
    if (this._registry[key]) {
      console.error(`Menu with the key '${key}' already exists. Either unregister it first or use a unique key.`);

      return;
    }

    // Add to registry
    this._registry[key] = menu;

    // Notify subject
    this._onMenuRegistered.next([key, menu]);
  }

  /**
   * Unregister the menu from the registry
   *
   * @param key
   */
  unregister(key): void {
    // Confirm if the menu exists
    if (!this._registry[key]) {
      console.warn(`Menu with the key '${key}' doesn't exist in the registry.`);
    }

    // Unregister sidebar
    delete this._registry[key];

    // Notify subject
    this._onMenuUnregistered.next(key);
  }

  /**
   * Get menu from registry by key
   *
   * @param key
   * @returns {any}
   */
  getMenu(key): any {
    // Confirm if the menu exists
    if (!this._registry[key]) {
      console.warn(`Menu with the key '${key}' doesn't exist in the registry.`);

      return;
    }

    // Return sidebar
    return this._registry[key];
  }

  /**
   * Get current menu
   *
   * @returns {any}
   */
  getCurrentMenu(): any {
    if (!this._currentMenuKey) {
      console.warn(`The current menu is not set.`);

      return;
    }

    return this.getMenu(this._currentMenuKey);
  }

  /**
   * Set menu with the key as the current menu
   *
   * @param key
   */
  setCurrentMenu(key): void {
    // Confirm if the sidebar exists
    if (!this._registry[key]) {
      console.warn(`Menu with the key '${key}' doesn't exist in the registry.`);

      return;
    }

    // Set current menu key
    this._currentMenuKey = key;

    // Notify subject
    this._onMenuChanged.next(key);
  }
}

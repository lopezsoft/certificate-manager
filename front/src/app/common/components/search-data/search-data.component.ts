import {AfterViewInit, Component, ElementRef, EventEmitter, HostListener, Input, Output, ViewChild} from '@angular/core';
import {Subject} from 'rxjs';
import {debounceTime} from 'rxjs/operators';

@Component({
  selector: 'app-search-data',
  templateUrl: './search-data.component.html',
  styleUrls: ['./search-data.component.scss']
})
export class SearchDataComponent implements AfterViewInit {
  @ViewChild('searchField') searchField: ElementRef<HTMLInputElement>;
  @Output() onSearch = new EventEmitter<string>();
  @Output() onApplyFilter = new EventEmitter<Set<string>>();
  @Output() onClearFilter = new EventEmitter();
  @Input() placeholder = 'Buscar';
  @Input() isLoading = false;
  @Input() showFilter = false;
  protected isFilterMenuOpen = false;
  protected searchIsClicked = false;
  protected filterCount = 0;
  private appliedFilters: Set<string> = new Set();
  private searchSubject = new Subject<string>();
  constructor() {
    this.placeholder = 'Buscar';
    this.isLoading = false;
    this.showFilter = false;
    this.searchSubject.pipe(
      debounceTime(350) // Retrasa la búsqueda
    ).subscribe({
      next: (query) => {
        this.searchQuery(query);
      }
    });
  }

  ngAfterViewInit(): void {
    if (this.searchField && this.searchField.nativeElement) {
      this.searchField.nativeElement.focus();
    }
  }
  @HostListener('document:click', ['$event'])
  onClickOutside(event: Event) {
    const targetElement = event.target as HTMLElement;
    if (!targetElement.closest('.search-container')) {
      this.closeFilterMenu();
    }
  }
  toggleFilterMenu() {
    this.isFilterMenuOpen = !this.isFilterMenuOpen;
  }

  closeFilterMenu() {
    this.isFilterMenuOpen = false;
  }

  applyFilter(option: string) {
    if (this.appliedFilters.has(option)) {
      this.appliedFilters.delete(option);
    } else {
      this.appliedFilters.add(option);
    }
    this.filterCount = this.appliedFilters.size;
  }

  getFilters() {
    return this.appliedFilters;
  }

  protected clearFilters() {
    this.appliedFilters.clear();
    this.filterCount = 0;
    this.searchIsClicked = false;
    this.onClearFilter.emit();
  }

  protected search() {
    this.onApplyFilter.emit(this.appliedFilters);
    this.searchIsClicked = true;
    this.searchQuery(this.searchField.nativeElement.value);
    this.closeFilterMenu();
  }

  protected canShowFilter(): boolean {
    return this.showFilter;
  }

  protected inputSearch(e: Event) {
    const ele = <HTMLInputElement> e.target;
    this.searchSubject.next(ele.value);
  }
  protected inputKey(event: any): void {
    const ts  = this;
    const ele = <HTMLInputElement> event.target;
    const searchString  = ele.value;
    if (event.keyCode === 13) {
      ts.searchQuery(searchString);
    }
  }
  protected refreshSearch(searchQuery: string): void {
    this.searchQuery(searchQuery);
    this.searchField.nativeElement.focus();
  }
  protected searchQuery(searchQuery: string): void {
    this.onSearch.emit(searchQuery);
  }

}

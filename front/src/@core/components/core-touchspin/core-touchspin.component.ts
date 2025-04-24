import {Component, OnInit, Input, ViewEncapsulation, Output, EventEmitter, AfterViewInit} from '@angular/core';
@Component({
  selector: 'core-touchspin',
  templateUrl: './core-touchspin.component.html',
  styleUrls: ['./core-touchspin.component.scss'],
  encapsulation: ViewEncapsulation.None
})
export class CoreTouchspinComponent implements OnInit, AfterViewInit {
  @Output() onChange  = new EventEmitter<number>();
  @Input('numberValue') numberValue = 0;

  @Input('iconChevron') iconChevron = false;
  @Input('disable') disabledValue = false;
  @Input('size') size = '';
  @Input('color') color = '';
  @Input('stepValue') stepValue: number;
  @Input('maxValue') maxValue = 9999;
  @Input('minValue') minValue = 0;
  protected isFocused = false;
  public disabledValueIncrement = false;
  public disabledValueDecrement = false;

  constructor() {}

  private changeValue(value: number) {
    this.onChange.emit(value);
  }
  inputChange(inputValue: number) {
    this.disabledValueIncrement = inputValue === this.maxValue || inputValue > this.maxValue;
    this.disabledValueDecrement = inputValue === this.minValue || inputValue < this.minValue;
    this.changeValue(inputValue);
  }

  increment() {
    if (!this.isFocused) { return; }
    let numberValue = parseFloat(this.numberValue.toString());
    if (this.stepValue === undefined) {
      numberValue += 1;
    } else {
      numberValue += parseFloat(this.stepValue.toString());
    }

    if (!(this.minValue === undefined || this.maxValue === undefined)) {
      this.disabledValueIncrement = numberValue === this.maxValue || numberValue > this.maxValue;
      this.disabledValueDecrement = numberValue <= this.minValue;
    }
    this.numberValue  = numberValue;
    this.changeValue(this.numberValue);
  }

  decrement() {
    if (!this.isFocused) { return; }
    let numberValue = parseFloat(this.numberValue.toString());
    if (this.stepValue === undefined) {
      numberValue -= 1;
    } else {
      numberValue -= parseFloat(this.stepValue.toString());
    }

    if (!(this.minValue === undefined || this.maxValue === undefined)) {
      this.disabledValueDecrement = numberValue === this.minValue || numberValue < this.minValue;
      this.disabledValueIncrement = numberValue >= this.maxValue;
    }
    this.numberValue  = numberValue;
    this.changeValue(this.numberValue);
  }

  ngOnInit(): void {
    this.disabledValueIncrement = this.disabledValue;
    this.disabledValueDecrement = this.disabledValue;
  }
  onFocus() {
    this.isFocused = true;
  }
  onBlur() {
    this.isFocused = false;
  }

  ngAfterViewInit(): void {
    this.disabledValueIncrement = this.disabledValue;
    this.disabledValueDecrement = this.disabledValue;
  }
}

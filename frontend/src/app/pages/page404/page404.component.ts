import { Component, Inject, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterModule, Router } from '@angular/router';

@Component({
  selector: 'app-page404',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './page404.component.html',
  styleUrls: ['./page404.component.css']
})
export class Page404Component {

  constructor(
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {}

  goBack(): void {
    if (isPlatformBrowser(this.platformId)) {
      window.history.back();
    }
  }
}
import { Component, OnInit, ChangeDetectorRef, AfterViewInit } from '@angular/core';
import { RouterOutlet, Router, NavigationStart, NavigationEnd, NavigationCancel, NavigationError } from '@angular/router';
import { CommonModule } from '@angular/common';
import { LoadingService } from './core/services/loading.service';
import { LoadingComponent } from './shared/components/loading/loading/loading.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, CommonModule, LoadingComponent],
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit, AfterViewInit {
  title = 'agent ia rédaction web';
  isLoading = false;
  private navigationLoading = false;

  constructor(
    private router: Router,
    private loadingService: LoadingService,
    private cdr: ChangeDetectorRef  // Injecter ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    // S'abonner aux événements de navigation
    this.router.events.subscribe(event => {
      if (event instanceof NavigationStart) {
        this.navigationLoading = true;
        this.isLoading = true;
        this.cdr.detectChanges();  // Forcer la détection immédiate
      } else if (event instanceof NavigationEnd || 
                 event instanceof NavigationCancel || 
                 event instanceof NavigationError) {
        this.navigationLoading = false;
        // Utiliser setTimeout pour éviter l'erreur ExpressionChangedAfterItHasBeenChecked
        setTimeout(() => {
          this.isLoading = false;
          this.cdr.detectChanges();
        }, 0);
      }
    });

    // S'abonner à l'état du loading service
    this.loadingService.isLoading$.subscribe(isLoading => {
      // Utiliser setTimeout pour éviter l'erreur
      setTimeout(() => {
        this.isLoading = isLoading || this.navigationLoading;
        this.cdr.detectChanges();
      }, 0);
    });
  }

  ngAfterViewInit(): void {
    // Forcer une détection après l'initialisation de la vue
    this.cdr.detectChanges();
  }
}
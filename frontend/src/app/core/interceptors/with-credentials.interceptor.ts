// with-credentials.interceptor.ts
import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../environments/environment';


export const withCredentialsInterceptor: HttpInterceptorFn = (req, next) => {
  if (req.url.includes(environment.apiUrl)) {
    req = req.clone({
      withCredentials: true
    });
  }
  return next(req);
};
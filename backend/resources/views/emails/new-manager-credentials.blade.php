<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos identifiants - Agent IA Rédaction Web</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f7f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <!-- Conteneur principal -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
                    
                    <!-- En-tête avec fond vert -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px 40px; text-align: center;">
                            <img src="https://benin-digital.com/wp-content/uploads/2021/08/cropped-Benin-digital-182.png" 
                                 alt="BENIN DIGITAL" 
                                 style="max-width: 80px; height: auto; margin-bottom: 10px;">
                            <h1 style="color: #2a7a4a; font-size: 24px; margin: 0; font-weight: 600;">Agent IA Rédaction Web</h1>
                        </td>
                    </tr>
                    
                    <!-- Corps -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #2a7a4a; font-size: 22px; margin-top: 0; margin-bottom: 20px;">Bienvenue sur la plateforme</h2>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Bonjour <strong>{{ $manager->firstname }} {{ $manager->name }}</strong>,
                            </p>
                            
                            <p style="color: #3a4a3a; font-size: 16px; line-height: 1.5; margin-bottom: 20px;">
                                Votre compte a été créé par l'administrateur. Voici vos identifiants de connexion :
                            </p>
                            
                            <!-- Encadré identifiants -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f8f2; border-radius: 12px; padding: 20px; margin: 25px 0;">
                                <tr>
                                    <td>
                                        <p style="margin: 5px 0; font-size: 15px;">
                                            <strong style="color: #2a7a4a;">Email :</strong> 
                                            <span style="color: #1a2a1a;">{{ $manager->email }}</span>
                                        </p>
                                        <p style="margin: 10px 0 0 0; font-size: 15px;">
                                            <strong style="color: #2a7a4a;">Mot de passe temporaire :</strong> 
                                            <span style="font-family: monospace; background: #e8ece8; padding: 4px 8px; border-radius: 6px; font-size: 14px;">{{ $tempPassword }}</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #e67e22; background-color: #fff3e0; padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 25px;">
                                <strong>Important :</strong> Nous vous recommandons de changer votre mot de passe dès votre première connexion.
                            </p>
                            
                            <!-- Bouton de connexion -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://redacteur.benin-digital.app/connexion" 
                                            style="display: inline-block; background-color: #3cb77c; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 10px 0;">
                                                Se connecter
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Pied de page -->
                    <tr>
                        <td style="background-color: #f5f7f5; padding: 25px 40px; text-align: center; border-top: 1px solid #e0e6e0;">
                            <p style="color: #6a7a6a; font-size: 12px; margin: 0 0 10px 0;">
                                © {{ date('Y') }} BENIN DIGITAL - Tous droits réservés
                            </p>
                            <p style="color: #8a9a8a; font-size: 11px; margin: 0;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                            <p style="color: #8a9a8a; font-size: 11px; margin: 5px 0 0 0;">
                                lot 85, rue 12.200. Haie Vive, Cotonou. Bénin
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
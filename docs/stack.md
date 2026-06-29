# Stack tecnológico

> Definido durante el **instalador**. Si iniciás un proyecto nuevo, la IA te
> preguntará y completará esta sección con tus decisiones. Lo de abajo es el
> **stack por defecto** de nsSkeleton.

## Stack por defecto

| Capa            | Tecnología                          | Notas                                            |
|-----------------|-------------------------------------|--------------------------------------------------|
| Lenguaje        | PHP 8.2+                            | Sin framework: **MVC propio** liviano            |
| Base de datos   | MySQL / MariaDB                    | `utf8mb4`, migraciones versionadas               |
| CSS             | Tailwind CSS (CLI standalone)      | Sin Node: binario standalone. Mobile-first       |
| JS              | Alpine.js + ES modules (vanilla)   | Reactividad sin build / sin toolchain pesado     |
| Gráficos        | Chart.js / ApexCharts              | Barras, torta, dashboards                         |
| Servidor        | Apache (XAMPP) / Nginx             | `.htaccess` para front controller                |
| Email           | SMTP (PHPMailer o cliente propio)  | Configurable desde el sistema base               |

### Por qué este stack

- **PHP puro + MVC propio**: máximo control, liviano, sin dependencias de framework,
  fácil de entender y deployar en hosting compartido / XAMPP.
- **Tailwind standalone + Alpine.js**: CSS/JS modernos sin necesidad de instalar Node,
  compatibles con Safari, Chrome, Edge y Brave, y excelentes en mobile.

---

## Stack elegido para ESTE proyecto

<!-- El instalador completa esto. Reemplazá si difiere del default. -->

- **Lenguaje:** _por completar_
- **Base de datos:** _por completar_
- **CSS/JS:** _por completar_
- **IA / herramienta agéntica:** _por completar_
- **Hosting / deploy:** _por completar_

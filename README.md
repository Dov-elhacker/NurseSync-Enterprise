#  NurseSync Enterprise

> **Smart Nurse Allocation System for Hospital Ward Operations**

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![SQL Server](https://img.shields.io/badge/SQL_Server-Database-CC2927?logo=microsoftsqlserver)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)

## Overview

NurseSync Enterprise is a web-based hospital workforce management system designed to improve nurse-to-patient allocation using a rule-based redistribution engine. The system automatically balances nursing staff according to patient load, department ratios, minimum staffing requirements, and specialty preferences.

---

## Features

-  Interactive Dashboard
-  Nurse Management
-  Patient Management
-  Department Management
-  Smart Nurse Redistribution
-  Charts & Statistics
-  Secure Authentication
-  CSRF Protection
-  Password Hashing
-  Responsive Interface

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP |
| Database | Microsoft SQL Server |
| Frontend | HTML5, CSS3, JavaScript |
| Charts | Chart.js |
| Security | Sessions, CSRF Tokens, Password Hashing |

---

## Smart Allocation Algorithm

The system automatically:

1. Calculates weighted patient load.
2. Computes required nurses using each department's ratio.
3. Applies minimum staffing rules.
4. Detects shortages and surpluses.
5. Redistributes available nurses.
6. Updates the dashboard instantly.

---

## Project Structure

```text
/
├── dashboard.php
├── login.php
├── manage_nurses.php
├── add_patient.php
├── departments.php
├── redistribute.php
├── discharge_patient.php
├── renew_data.php
├── config.php
├── db_connect.php
├── redistribution_helper.php
├── style.css
└── assets/
```

---

## Installation

```bash
[git clone https://github.com/yourusername/NurseSync-Enterprise.git
](https://github.com/Dov-elhacker/NurseSync-Enterprise)```

Create a SQL Server database.

Import the project database.

Update `db_connect.php`.

Run using XAMPP/IIS/Apache with SQLSRV enabled.

---

## Database

Main tables:

- Departments
- Nurses
- Patients

---

## Screenshots<img width="1920" height="1080" alt="Screenshot (1124)" src="https://github.com/user-attachments/assets/91762888-afbf-425f-a775-2d4bf79d54e6" />


Replace these placeholders after uploading screenshots.

```
<img width="1920" height="1080" alt="Screenshot (1115)" src="https://github.com/user-attachments/assets/aeef67be-847a-43d1-8ea3-b57e75bd0f70" />

<img width="1920" height="1080" alt="Screenshot (1116)" src="https://github.com/user-attachments/assets/a4f9d33e-e8f0-4ea6-a784-28ca21e3ff8e" />

<img width="1920" height="1080" alt="Screenshot (1117)" src="https://github.com/user-attachments/assets/70d62ca8-ba66-4a1f-84ae-df94f13bef4b" />
<img width="1920" height="1080" alt="Screenshot (1118)" src="https://github.com/user-attachments/assets/0416f980-8c8f-4400-acdc-f1627739f7dd" />
<img width="1920" height="1080" alt="Screenshot (1119)" src="https://github.com/user-attachments/assets/e985c895-d385-409b-ac1a-a3b69f1fdaca" />
<img width="1920" height="1080" alt="Screenshot (1120)" src="https://github.com/user-attachments/assets/101ff65e-da80-40c7-9416-c6ac6888d048" />
<img width="1920" height="1080" alt="Screenshot (1121)" src="https://github.com/user-attachments/assets/cace3c31-7410-4ef6-b6a1-14f4bb7abb4a" />
<img width="1920" height="1080" alt="Screenshot (1122)" src="https://github.com/user-attachments/assets/e7cded01-281b-4573-b687-3b058a20d1d7" />
<img width="1920" height="1080" alt="Screenshot (1123)" src="https://github.com/user-attachments/assets/35f60440-2989-478f-8613-a144e4bfaa54" />
<img width="1920" height="1080" alt="Screenshot (1124)" src="https://github.com/user-attachments/assets/d571f2b8-88ea-4ffa-8626-6b842b0fab3b" />
```

---

## Security

- Password Hashing
- Session Authentication
- CSRF Protection
- Input Validation

---

## Future Improvements

- AI-based prediction
- Shift scheduling
- Notifications
- REST API
- Mobile Application

---

## Contributing

Pull requests are welcome.

---

## License

MIT License

---

## Author

**David Wagih**

Computer Science Student • Alexandria University

Aspiring Data Engineer

---

 If you found this project useful, consider giving it a star!

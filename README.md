# 🏥 NurseSync Enterprise

> **Smart Nurse Allocation System for Hospital Ward Operations**

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![SQL Server](https://img.shields.io/badge/SQL_Server-Database-CC2927?logo=microsoftsqlserver)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)

## 📖 Overview

NurseSync Enterprise is a web-based hospital workforce management system designed to improve nurse-to-patient allocation using a rule-based redistribution engine. The system automatically balances nursing staff according to patient load, department ratios, minimum staffing requirements, and specialty preferences.

---

## ✨ Features

- 📊 Interactive Dashboard
- 👩‍⚕️ Nurse Management
- 🧑‍🤝‍🧑 Patient Management
- 🏥 Department Management
- 🔄 Smart Nurse Redistribution
- 📈 Charts & Statistics
- 🔐 Secure Authentication
- 🛡️ CSRF Protection
- 🔑 Password Hashing
- 📱 Responsive Interface

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP |
| Database | Microsoft SQL Server |
| Frontend | HTML5, CSS3, JavaScript |
| Charts | Chart.js |
| Security | Sessions, CSRF Tokens, Password Hashing |

---

## ⚙️ Smart Allocation Algorithm

The system automatically:

1. Calculates weighted patient load.
2. Computes required nurses using each department's ratio.
3. Applies minimum staffing rules.
4. Detects shortages and surpluses.
5. Redistributes available nurses.
6. Updates the dashboard instantly.

---

## 📂 Project Structure

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

## 🚀 Installation

```bash
git clone https://github.com/yourusername/NurseSync-Enterprise.git
```

Create a SQL Server database.

Import the project database.

Update `db_connect.php`.

Run using XAMPP/IIS/Apache with SQLSRV enabled.

---

## 🗄 Database

Main tables:

- Departments
- Nurses
- Patients

---

## 📸 Screenshots

Replace these placeholders after uploading screenshots.

```
images/dashboard.png
images/manage-nurses.png
images/patients.png
images/departments.png
```

---

## 🔒 Security

- Password Hashing
- Session Authentication
- CSRF Protection
- Input Validation

---

## 🔮 Future Improvements

- AI-based prediction
- Shift scheduling
- Notifications
- REST API
- Mobile Application

---

## 🤝 Contributing

Pull requests are welcome.

---

## 📜 License

MIT License

---

## 👨‍💻 Author

**David Wagih**

Computer Science Student • Alexandria University

Aspiring Data Engineer

---

⭐ If you found this project useful, consider giving it a star!

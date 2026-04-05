# 💧 AQUALITICS — IoT-Based Real-Time Tap Water Quality Monitoring System

> **Capstone Research Project** | Bachelor of Science in Information Technology  
> Don Mariano Marcos Memorial State University – Mid La Union Campus  
> College of Information Technology | City of San Fernando, La Union, Philippines  
> **November 2025**

---

## 👥 Research Team

| Name | Role |
|------|------|
| **Bryan D. Mamuyac Jr.** | Full-Stack Web Developer & Arduino/IoT Developer |
| Eric Brandon B. Gurion | Researcher |
| Queenie Leanne P. Flores | Researcher |
| Angel Laurence S. Gatchallian | Researcher |

> **Adviser:** Manny R. Hortizuela, PhD

---

## 📌 Overview

**Aqualitics** is an IoT-based real-time monitoring system designed to assess and analyze the key physical parameters of tap water — **temperature, pH level, turbidity, total dissolved solids (TDS), and electrical conductivity (EC)** — visualized through a web-based dashboard for efficient monitoring and analysis.

The system was developed in direct response to the Philippines' low water quality standing (ranked 115/180 globally by Yale's EPI 2024) and is aligned with the **UN Sustainable Development Goal 6 (SDG 6): Clean Water and Sanitation**. It serves as a practical, accessible tool for water districts like the **Metro San Fernando Water District** to monitor tap water quality in real time without the need for lengthy laboratory testing.

The system achieved a **very high TAP-TEEPS validity score of 4.51** and high sensor accuracy, confirming its reliability as a water monitoring solution.

---

## 🖥️ System Screenshots

### Login Dashboard
![Login Dashboard](docs/Login-Dashboard.jpg)

### User Dashboard
![User Dashboard](docs/User-Dashboard.jpg)

### Admin Dashboard
![Admin Dashboard](docs/Admin-Dashboard.jpg)

### User Profile Dashboard
![User Profile](docs/User-Profile-Dashboard.jpg)

### Water Quality Thresholds Management
![Thresholds](docs/Water-Quality-Thresholds.jpg)

### Real-Time Data Fetching
![Fetching Data](docs/Fetching-Data.jpg)

### Interval Time Data Fetching
![Interval Fetching](docs/Interval-Fetching.jpg)

### AI Support Bot
![Support Bot](docs/Support-Bot.jpg)

### User Manual (IoT)
![User Manual](docs/User-Manual-IOT.jpg)

### System Documentation
![Documentation 1](docs/Documentation-Process-1.jpg)
![Documentation 2](docs/Documentation-Process-2.jpg)

---

## ⚙️ Tech Stack

### 🌐 Web Development (Full-Stack)
| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Database | MySQL (managed via SqlYog) |
| Web Server | XAMPP (Apache) — local; **Hostinger** — online hosting |
| Code Editor | Visual Studio Code |
| AI Chatbot | Integrated Aqualitics Support Bot |

### 🔧 Hardware / IoT (Arduino)
| Component | Details |
|-----------|---------|
| Microcontroller | Arduino Uno R3 |
| Communication | Ethernet Module + Router |
| Display | OLED Display Module |
| Enclosure | Waterproof Plastic Box (Electronic IP67) |
| Connectivity | Local Network via Ethernet Shield |

### 📡 Sensors
| Sensor | Parameter Measured | Safe Range |
|--------|-------------------|------------|
| Temperature Sensor (DS18B20) | Water Temperature | 15°C – 60°C |
| pH Sensor | Acidity/Alkalinity | 6.5 – 8.5 |
| DFRobot Analog Turbidity Sensor | Water Clarity | Standard NTU range |
| Analog TDS Sensor | Total Dissolved Solids | Lower = purer |
| Electrical Conductivity (via TDS) | Ionic content | 50 – 800 µS/cm |

### 🎨 Design & Prototyping Tools
- **Fritzing** — Schematic diagram design
- **Creately** — Use case diagram
- **SketchUp** — 3D device prototype modeling

---

## 🏗️ System Architecture

The Aqualitics system follows a three-tier architecture:

```
[ IoT Device (Arduino + Sensors) ]
            |
            | Ethernet / Network
            ▼
[ PHP Backend + MySQL Database ]
            |
            | HTTP
            ▼
[ Web Dashboard (HTML/CSS/JS/PHP) ]
     ├── User Dashboard
     ├── Admin Dashboard
     ├── Water Quality Threshold Manager
     ├── AI Support Chatbot
     └── User Profile & Management
```

---

## 📂 Project Structure

```
Aqualitics_Official/
├── api/                    # API endpoints for IoT data ingestion
├── config/                 # Database and app configuration
├── css/                    # Stylesheets
├── docs/                   # System documentation & screenshots
├── includes/               # Reusable PHP components/partials
├── js/                     # JavaScript files
├── logs/                   # System logs
├── PHPMailer/              # Email library
├── vendor/                 # Composer dependencies
├── admin_dashboard.php     # Admin panel
├── dashboard.php           # User dashboard (real-time data visualization)
├── index.php               # Login / Landing page
├── logout.php              # Session termination
├── profile.php             # User profile management
├── register.php            # User registration
├── test_pairing.php        # IoT device pairing test
├── verify.php              # Email/account verification
├── Aqualitics_logo.png     # System logo
├── composer.json           # PHP dependencies
└── .gitignore
```

---

## 🔬 Water Parameters Monitored

| Parameter | Safe Range | Why It Matters |
|-----------|-----------|----------------|
| **Temperature** | 15°C – 60°C | Affects other parameters; extreme temps pose health risks |
| **pH Level** | 6.5 – 8.5 | Indicates acidity/alkalinity; outside range signals contamination |
| **Turbidity** | Low NTU | Measures water clarity; high turbidity = sediment/contamination |
| **Total Dissolved Solids (TDS)** | Lower = better | Concentration of dissolved minerals/salts |
| **Electrical Conductivity (EC)** | 50 – 800 µS/cm | Related to TDS; measures ionic content |

---

## 🎯 Key Features

- ⚡ **Real-Time Data Fetching** — Sensor readings pushed to the dashboard at configurable intervals
- 📊 **Data Visualization** — Charts and graphs for each water parameter
- 🔔 **Threshold Alerts** — Automatic alerts when parameters fall outside safe ranges
- 🤖 **AI Support Chatbot** — Aqualitics bot for user guidance and system queries
- 👤 **Role-Based Access** — Separate interfaces for Admin and regular Users
- 👥 **User Management** — Registration, verification, and profile management
- 🌐 **Dual Hosting** — Runs on both local XAMPP and live Hostinger server
- 📋 **System Logging** — Tracks data and events for audit/debugging purposes

---

## 🧪 Evaluation Results

### TAP-TEEPS (Technology Assessment Protocol)
| Criterion | Score | Interpretation |
|-----------|-------|----------------|
| Technical Performance | High | Device performs reliably |
| Economic Viability | High | Cost-effective at ₱5,105 total hardware cost |
| Environmental Soundness | High | Minimal environmental impact |
| Political Acceptability | High | Compliant with PNSDW 2017 & WHO standards |
| Social Acceptability | High | Well-received by water district personnel |
| **Overall Score** | **4.51** | **Very High Validity** |

### System Usability Scale (SUS)
- Administered to end users of the web dashboard
- Results indicated **high usability** and user-friendliness

### Sensor Accuracy
- Validated against **DOST (Department of Science and Technology)** reference readings
- Accuracy measured using **Mean Absolute Error (MAE)** and **Mean Absolute Percentage Error (MAPE)**
- Results confirmed sensors operate within acceptable accuracy thresholds

---

## 🛠️ Hardware Bill of Materials

| # | Component | Unit Price | Qty | Total |
|---|-----------|-----------|-----|-------|
| 1 | Arduino Uno R3 | ₱587.00 | 1 | ₱587.00 |
| 2 | Breadboard | ₱100.00 | 1 | ₱100.00 |
| 3 | Temperature Sensor | ₱193.00 | 1 set | ₱193.00 |
| 4 | pH Sensor | ₱486.00 | 1 set | ₱486.00 |
| 5 | Analog TDS Sensor | ₱302.00 | 1 set | ₱302.00 |
| 6 | DFRobot Analog Turbidity Sensor | ₱761.00 | 1 | ₱761.00 |
| 7 | OLED Display Module | ₱350.00 | 1 | ₱350.00 |
| 8 | Ethernet Module | ₱400.00 | 1 | ₱400.00 |
| 9 | Arduino Shield | ₱300.00 | 1 | ₱300.00 |
| 10 | Waterproof Plastic Enclosure (IP67) | ₱614.00 | 1 | ₱614.00 |
| 11 | LED 5mm Red & Green | ₱4.00 | 10 | ₱40.00 |
| 12 | Buzzer | ₱90.00 | 1 | ₱90.00 |
| 13 | Switching Power Supply 2A 9VDC | ₱132.00 | 1 | ₱132.00 |
| 14 | 100R 5% Resistor | ₱1.40 | 100 | ₱140.00 |
| 15 | Jumper Wires 20cm (M-M & F-M) | ₱78.00 | 2 sets | ₱156.00 |
| 16 | Jumper Wires 10cm (M-M & F-M) | ₱47.50 | 4 sets | ₱190.00 |
| 17 | Router | ₱90.00 | 1 | ₱90.00 |
| | | | **Grand Total** | **₱4,931.00** |

---

## 🚀 Local Setup (XAMPP)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 7.4+ / 8.x, Apache, MySQL)
- A web browser
- Arduino IDE (for uploading firmware to the Arduino device)

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/Bryan-Mamuyac/AQUALITICS-IoT-BASED-REAL-TIME-MONITORING-FOR-TAP-WATER-QUALITY-ASSESSMENT.git
   ```

2. **Move to XAMPP htdocs**
   ```bash
   move AQUALITICS-IoT-BASED-REAL-TIME-MONITORING-FOR-TAP-WATER-QUALITY-ASSESSMENT C:\xampp\htdocs\Aqualitics_Official
   ```

3. **Import the database**
   - Open `http://localhost/phpmyadmin`
   - Create a new database (e.g., `aqualitics_db`)
   - Import the provided `.sql` file from the `config/` folder

4. **Configure database connection**
   - Edit `config/db.php` with your local MySQL credentials:
   ```php
   $host = 'localhost';
   $dbname = 'aqualitics_db';
   $username = 'root';
   $password = '';
   ```

5. **Start Apache and MySQL in XAMPP**

6. **Access the system**
   ```
   http://localhost/Aqualitics_Official/
   ```

### Arduino Setup
1. Open Arduino IDE
2. Load the firmware from the `arduino/` directory
3. Install required libraries:
   - `Ethernet`
   - `OneWire`
   - `DallasTemperature`
4. Configure the server IP to match your local machine
5. Upload to Arduino Uno R3

---

## 🌐 Live Deployment

The system was deployed and tested on **Hostinger** for remote access, allowing the IoT device to send data to the live web server and enabling real-time remote monitoring from any browser.

---

## 📚 Research Context

| Detail | Info |
|--------|------|
| Research Type | Descriptive + Applied |
| Development Model | Evolutionary Prototype Model |
| Evaluation Tools | TAP-TEEPS + System Usability Scale (SUS) |
| Standards Referenced | WHO Guidelines for Drinking-Water Quality, PNSDW 2017 |
| Aligned With | UN SDG 6 — Clean Water and Sanitation |
| Testing Site | Metro San Fernando Water District, City of San Fernando, La Union |
| Academic Year | 2024–2025 to 2025–2026 |

---

## 📄 License

This project was developed as an academic capstone. All rights reserved by the research team and Don Mariano Marcos Memorial State University – Mid La Union Campus.

---

> *"Don't get set into one form, adapt it and build your own, and let it grow, be like water."*  
> — Bruce Lee
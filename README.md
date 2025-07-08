# 🔗 Referral Integration & Activator – WordPress Plugin Suite

This project includes **two lightweight WordPress plugins** that work together to manage a full referral tracking and activation system:

1. **Referral Integration Plugin** – Tracks referral data using URL parameters or cookies  
2. **Referral Activator Plugin** – Detects successful conversion and triggers actions (e.g., apply discount, send webhook, tag user)

Together, they allow store owners or marketers to run **affiliate/referral campaigns inside WordPress** without relying on third-party SaaS tools.

---

## 🧩 How They Work Together

- A visitor arrives with a referral link → Referral Integration stores the referrer (via UTM, cookies, session)
- When the visitor completes a defined action (e.g., makes a purchase, submits a form) → Referral Activator triggers your defined logic (e.g., reward referrer, apply discount, send notification)

---

## ⚙️ Key Features

✅ **Referral Integration Plugin**  
- Track UTM or custom referral links  
- Store data via cookies or WordPress user meta  
- Flexible for use in WooCommerce or custom sites  

✅ **Referral Activator Plugin**  
- Detects user actions like order completion or form submission  
- Triggers referral logic (discounts, CRM tags, webhooks, etc.)  
- Uses WordPress hooks for easy extension

---

## 💻 Technologies Used

- PHP (WordPress Plugin API, OOP structure)  
- JavaScript (optional frontend events)  
- WordPress DB functions  
- WooCommerce compatibility  
- REST & Webhook-ready

---

## 🔧 Installation

1. Upload both plugin folders to `/wp-content/plugins/`  
2. Activate both via WordPress admin  
3. Go to **Referral Settings** to configure referral tracking and activation rules

---

## 📌 Use Cases

- Affiliate campaigns  
- Influencer marketing  
- Loyalty programs  
- CRM tagging & automation (e.g., with Zapier, Mailchimp)

---

## ✅ Project Status

Live and used in production. Modular and ready for extension via custom hooks or integrations.

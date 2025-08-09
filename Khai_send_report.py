import pandas as pd
import matplotlib.pyplot as plt
import re
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.mime.application import MIMEApplication
from datetime import datetime, timedelta

# ====== Cấu hình ======
LOG_FILE = "backend/speedtest_results.log"
EMAIL_SENDER = "phuocscpy@gmail.com"
EMAIL_PASSWORD = "zoelpdyuokwrknnp"  # App password, không dùng password Gmail trực tiếp
EMAIL_RECEIVER = "phuocscpy@gmail.com"
REPORT_PERIOD_DAYS = 1  # 1 = hàng ngày, 7 = hàng tuần
# ======================

# Đọc file log
pattern = re.compile(
    r"(?P<time>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \| IP: (?P<ip>[\d\.]+) \| Download: (?P<dl>[\d\.]+) Mbps \| Upload: (?P<ul>[\d\.]+) Mbps \| Ping: (?P<ping>[\d\.]+) ms"
)

records = []
with open(LOG_FILE, "r") as f:
    for line in f:
        match = pattern.match(line.strip())
        if match:
            records.append(match.groupdict())

df = pd.DataFrame(records)
df["time"] = pd.to_datetime(df["time"])
df["dl"] = df["dl"].astype(float)
df["ul"] = df["ul"].astype(float)
df["ping"] = df["ping"].astype(float)

# Lọc theo khoảng thời gian
start_date = datetime.now() - timedelta(days=REPORT_PERIOD_DAYS)
df = df[df["time"] >= start_date]

if df.empty:
    print("Không có dữ liệu trong khoảng thời gian.")
    exit()

# Vẽ biểu đồ
plt.figure(figsize=(10, 6))
plt.plot(df["time"], df["dl"], label="Download (Mbps)", marker="o")
plt.plot(df["time"], df["ul"], label="Upload (Mbps)", marker="o")
plt.xlabel("Thời gian")
plt.ylabel("Tốc độ (Mbps)")
plt.title("Báo cáo tốc độ mạng")
plt.legend()
plt.grid(True)
plt.tight_layout()
plt.savefig("report_chart.png")

# Lưu Excel
excel_path = "report_data.xlsx"
df.to_excel(excel_path, index=False)

# Nội dung email
subject = f"Báo cáo tốc độ mạng {REPORT_PERIOD_DAYS} ngày gần nhất"
body = f"""
Xin chào,

Đây là báo cáo tốc độ mạng {REPORT_PERIOD_DAYS} ngày gần nhất.

📊 Thống kê:
- Số lần đo: {len(df)}
- Download trung bình: {df['dl'].mean():.2f} Mbps
- Upload trung bình: {df['ul'].mean():.2f} Mbps
- Ping trung bình: {df['ping'].mean():.2f} ms

File đính kèm:
- report_chart.png (biểu đồ)
- report_data.xlsx (dữ liệu chi tiết)

Trân trọng,
Hệ thống LibreSpeed
"""

# Gửi email
msg = MIMEMultipart()
msg["From"] = EMAIL_SENDER
msg["To"] = EMAIL_RECEIVER
msg["Subject"] = subject
msg.attach(MIMEText(body, "plain"))

# Đính kèm file
for file_path in ["report_chart.png", excel_path]:
    with open(file_path, "rb") as f:
        part = MIMEApplication(f.read(), Name=file_path)
        part["Content-Disposition"] = f'attachment; filename="{file_path}"'
        msg.attach(part)

# Gửi qua Gmail SMTP
with smtplib.SMTP_SSL("smtp.gmail.com", 465) as server:
    server.login(EMAIL_SENDER, EMAIL_PASSWORD)
    server.sendmail(EMAIL_SENDER, EMAIL_RECEIVER, msg.as_string())

print("✅ Đã gửi báo cáo thành công!")
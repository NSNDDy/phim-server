# Deploy Laravel Movie Site On AWS EC2

Use this for lawful movie content only: content you own, licensed content, or a catalog/trailer/metadata site.

## 1. Create EC2

1. AWS Console -> EC2 -> Launch instance.
2. AMI: Ubuntu Server 24.04 LTS or 22.04 LTS.
3. Instance type: `t3.micro` or `t2.micro` for small testing.
4. Storage: 20 GB is a reasonable starting point.
5. Security Group:
   - SSH `22`: your IP only.
   - HTTP `80`: `0.0.0.0/0`.
   - HTTPS `443`: `0.0.0.0/0` if you add SSL later.
   - Do not open MySQL `3306`.

## 2. Install Docker On EC2

```bash
sudo apt update
sudo apt install -y ca-certificates curl git unzip
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker ubuntu
```

Log out, then SSH back in so the `docker` group applies.

## 3. Upload Source

From your local machine:

```bash
cd /home/duy-syn/Documents/Test
tar --exclude='phim/storage/logs/*.log' --exclude='phim/storage/framework/sessions/*' -czf phim.tar.gz phim
scp -i /path/to/key.pem phim.tar.gz ubuntu@YOUR_EC2_PUBLIC_IP:/home/ubuntu/
```

On EC2:

```bash
tar -xzf phim.tar.gz
cd phim
cp .env.production.example .env
nano .env
```

Set:

```dotenv
APP_URL=http://YOUR_EC2_PUBLIC_IP
DB_PASSWORD=use_a_strong_password_here
```

## 4. Start App

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
docker compose -f docker-compose.prod.yml exec app php artisan storage:link
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

Open:

```text
http://YOUR_EC2_PUBLIC_IP
```

## 5. Import Existing Database If Needed

If your movie data is in an SQL dump, upload it to EC2, then run:

```bash
docker compose -f docker-compose.prod.yml exec -T db mysql -uroot -p phim < dump.sql
```

It will ask for the `DB_PASSWORD` from `.env`.

## 6. Basic Operations

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f nginx
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d --build
```

## 7. AWS Cost Guardrails

Create AWS Budgets alerts at low thresholds such as `$5`, `$20`, and `$50`.
Stop or terminate the EC2 instance when you are not testing.

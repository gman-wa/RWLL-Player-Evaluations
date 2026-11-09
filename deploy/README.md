# Deploying RWLL Evals

```
Cloudflare (proxied) ──443──▶ host Apache on the EC2 (host-apache/rwll-evals.conf)
                                   │ ProxyPass → 127.0.0.1:8082      (Football-NG is on 8081)
                                   ▼
                    docker compose (compose.yaml, /opt/rwll-evals)
                      app: Apache + PHP-FPM 8.5 ──▶ RDS (RWLL)
                                                ──▶ S3 player photos (instance role)
```

This is the same pipeline as Football-NG (`../Football-NG/deploy/README.md`), with its own ECR repository, secret and
deploy role. It runs on the same EC2 and uses the same instance role and Datadog agent.

A push to `main` runs `.github/workflows/deploy.yml`:

1. A syntax check of the PHP, JS and shell scripts. There is no test suite.
2. An arm64 image built and pushed to ECR as `rwll-evals:<commit sha>`.
3. An SSM command on the EC2. It copies `compose.yaml` and `deploy.sh` out of that image and runs `deploy.sh`, which:
   - writes the Secrets Manager JSON to `/etc/rwll-evals/settings.json`, which the container reads as `/run/secrets/settings`;
   - starts the new image;
   - waits for `127.0.0.1:8082/api/player/1` to answer (a player lookup, so it checks RDS too);
   - goes back to the previous image if it doesn't.
4. A GitHub release, if the deploy worked and the top `## x.y.z - title` section of `CHANGELOG.md` isn't tagged
   yet: tag `vx.y.z` on the deployed commit, with that section as the release notes.

## One-time setup

Replace `ACCOUNT` and `i-INSTANCE` below. The region is us-west-2. Steps marked *(done for Football)* need nothing new.

### 1. ECR repository

The same settings as `football-ng`: mutable tags, AES-256, keep 15 images.

```sh
aws ecr create-repository --repository-name rwll-evals --image-tag-mutability MUTABLE
aws ecr put-lifecycle-policy --repository-name rwll-evals --lifecycle-policy-text \
  '{"rules":[{"rulePriority":1,"selection":{"tagStatus":"any","countType":"imageCountMoreThan","countNumber":15},"action":{"type":"expire"}}]}'
```

If basic scanning on push is filtered to `football-ng`, add `rwll-evals` to the filter.

### 2. The settings secret

One Secrets Manager secret named `rwll-evals`, "Other type of secret". Key names are case-sensitive; `deploy.sh` refuses to deploy if any of these are missing.

| key | |
|---|---|
| `DB_HOST` | the RDS endpoint |
| `DB_USER` | the existing RWLL database user |
| `DB_PASSWORD` | its password |
| `ADMIN_PIN` | the `/admin` PIN; make it long, it's on the internet |

Optional keys: `DB_NAME` (default `RWLL`). Any other setting in `CLAUDE.md` can go here too, but the non-secret ones live in `compose.yaml`.

```sh
aws secretsmanager create-secret --name rwll-evals --secret-string file://settings.json
rm settings.json
```

To change a value, edit it in the console and deploy again (re-run the workflow). The app only sees the secret as of the last deploy.

**The database user** needs `SELECT, INSERT, UPDATE` on `RWLL`. The old app already used it with those grants, and the container reaches RDS from the EC2's own address, so the user's host part still matches. Check with `SHOW GRANTS FOR '<user>'@'<host>';`.

### 3. The EC2's instance role

*(done for Football)* SSM, `ecr:GetAuthorizationToken`, and the IMDS hop limit of 2 that lets containers use the role.
Football's ECR pull permissions are scoped to its own repository, so this repository needs its own.

Add a second inline policy, `rwll-evals` (the secret's name is in the `secret:` part of the ARN; `-*` matches the random
suffix Secrets Manager adds):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {"Sid": "PullRwllImages", "Effect": "Allow",
     "Action": ["ecr:BatchGetImage", "ecr:GetDownloadUrlForLayer", "ecr:BatchCheckLayerAvailability"],
     "Resource": "arn:aws:ecr:us-west-2:ACCOUNT:repository/rwll-evals"},
    {"Sid": "ReadRwllSettings", "Effect": "Allow", "Action": "secretsmanager:GetSecretValue",
     "Resource": "arn:aws:secretsmanager:us-west-2:ACCOUNT:secret:rwll-evals-*"},
    {"Sid": "UploadPlayerPhotos", "Effect": "Allow", "Action": ["s3:PutObject", "s3:PutObjectAcl"],
     "Resource": "arn:aws:s3:::cdn.cybernode.com/i/rwll/player-images/*"}
  ]
}
```

- `s3:PutObjectAcl` is there because photos are uploaded `public-read`, the same as the old app. If the bucket has ACLs disabled (Object Ownership "Bucket owner enforced") and is public through a bucket policy instead, set `S3_ACL: none` in `compose.yaml` and drop that action.
- **If `cdn.cybernode.com` is in a different AWS account** from the EC2, the role can't write to it without a bucket policy there. The simpler way is to put an IAM user's keys (`s3:PutObject` / `s3:PutObjectAcl` on that prefix only) in the secret as `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`. `Photos::putS3()` uses them instead of the role when they're set.

### 4. Docker on the EC2

*(done for Football)* Docker, the compose plugin, jq, swap and the SSM agent.

### 5. The host's Apache

1. **The vhost and its certificate.** The testing hostname is `rwll-evals.cybernode.com`, and it gets its own Let's Encrypt certificate, the same way as football-ng.
   1. Copy `deploy/host-apache/rwll-evals.conf` to `/etc/httpd/conf.d/`, with the 443 vhost commented out for now.
   2. Run `sudo certbot certonly --apache -d rwll-evals.cybernode.com`.
   3. Enable the 443 vhost.
2. *(done for Football)* `cloudflare-ips.inc` and the trusted proxy line in `httpd.conf`.
3. **Check and reload:**
   ```sh
   sudo apachectl configtest && sudo systemctl reload httpd
   ```
4. **Cloudflare:**
   - DNS: a proxied record for `rwll-evals`.
   - **Cache bypass:** add the hostname to the bypass rule that Football's hostnames are in. Without it, the zone's cache rule caches `/api/player/*` lookups and the admin pages, so a coach would see stale "already scored" answers. Check that this returns `DYNAMIC`:
     ```sh
     curl -sD - -o /dev/null https://rwll-evals.cybernode.com/api/player/1 | grep cf-cache-status
     ```
     `/assets/*` can stay cached; those urls carry a content hash.

The `LimitRequestBody` in the vhost lets a registration photo through. The phone normally sends about 60KB, but an unshrunk photo can be several MB.

### 6. GitHub → AWS (OIDC)

*(done for Football)* The identity provider `token.actions.githubusercontent.com`; there's one per account.

Create the role `rwll-evals-deploy`. Its trust policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {"Federated": "arn:aws:iam::ACCOUNT:oidc-provider/token.actions.githubusercontent.com"},
    "Action": "sts:AssumeRoleWithWebIdentity",
    "Condition": {
      "StringEquals": {"token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
                       "token.actions.githubusercontent.com:sub": "repo:gman-wa/RWLL-Player-Evaluations:ref:refs/heads/main"}
    }
  }]
}
```

And its permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {"Effect": "Allow", "Action": "ecr:GetAuthorizationToken", "Resource": "*"},
    {"Effect": "Allow", "Resource": "arn:aws:ecr:us-west-2:ACCOUNT:repository/rwll-evals",
     "Action": ["ecr:BatchCheckLayerAvailability", "ecr:BatchGetImage", "ecr:CompleteLayerUpload", "ecr:GetDownloadUrlForLayer",
                "ecr:InitiateLayerUpload", "ecr:PutImage", "ecr:UploadLayerPart"]},
    {"Effect": "Allow", "Action": "ssm:SendCommand",
     "Resource": ["arn:aws:ec2:us-west-2:ACCOUNT:instance/i-INSTANCE", "arn:aws:ssm:us-west-2::document/AWS-RunShellScript"]},
    {"Effect": "Allow", "Action": "ssm:GetCommandInvocation", "Resource": "*"}
  ]
}
```

### 7. GitHub repository variables

On the RWLL repo, under Settings > Secrets and variables > Actions > **Variables**. None of them are secret.

| variable | value |
|---|---|
| `AWS_REGION` | `us-west-2` |
| `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::ACCOUNT:role/rwll-evals-deploy` |
| `ECR_REPOSITORY` | `rwll-evals` |
| `EC2_INSTANCE_ID` | `i-INSTANCE` (the same instance as Football) |
| `SETTINGS_SECRET_ID` | the `rwll-evals` secret's ARN (or name) |
| `BUILD_RUNNER` | optional: `ubuntu-24.04-arm` builds natively |

### 8. Datadog

*(done for Football)* The agent on the EC2 already has:
- `logs_enabled: true`;
- `apm_non_local_traffic: true`;
- `dd-agent` in the docker group.

It picks up the new container from its labels with no agent change.

The container's logs come in two forms:
- **Apache access lines**: source `apache`, service `rwll-evals`, with the deploy and docker health checks dropped.
- **The app's error lines**: JSON from `log_error()` in `src/bootstrap.php`, with the trace ids in a top-level `dd` field, so each one links to its trace.

Traces come from the ddtrace extension in the image. After the first deploy, `sudo datadog-agent status` should list the rwll-evals container under Logs Agent, and APM → Services should show `rwll-evals` after a few page loads.

## Day to day

```sh
cd /opt/rwll-evals
sudo docker compose ps                    # running, and healthy?
sudo docker compose logs -f app           # Apache access + PHP errors + the app's JSON error lines
cat .env                                  # IMAGE= what's running
```

To roll back, either re-run an older successful workflow run in GitHub, or run this on the server:

```sh
sudo bash /opt/rwll-evals/deploy.sh ACCOUNT.dkr.ecr.us-west-2.amazonaws.com/rwll-evals:<older sha> rwll-evals
```

## Cutover from the old native site

The old PHP app is still served by the host's Apache. Do the cutover before the next evaluation day:

The live hostname is `rwll.garth.org` (proxied by Cloudflare in the garth.org zone). The league's own domain is a CNAME to it.

1. Try `rwll-evals.cybernode.com` on a phone at a field: register, run every station and the admin CSV.
2. Point the old site's vhost at the container. Edit `rwll-evals.conf`:
   - `ServerName rwll.garth.org`, in both vhosts and in the port 80 `RewriteCond`;
   - the certificate paths of the old vhost;
   - the old vhost's `ServerAlias` lines, if any.

   Requests that come in through the league's CNAME arrive with the league hostname as `Host`, so they only reach this vhost through such a `ServerAlias`. Copy whatever the old vhost does for them, including any certificate that covers that name. Then remove the old vhost and reload httpd.
3. **Cloudflare, garth.org zone:** add `rwll.garth.org` to a cache bypass for everything but `/assets/*`, then purge it. The zone caches the HTML today (`cf-cache-status: MISS` on `/`), so without the bypass `/api/player/*` lookups and the admin pages get cached. If the league's hostname goes through Cloudflare too, it needs the same bypass. Check each hostname with the `curl ... | grep cf-cache-status` from step 5 of the setup.
4. Check that the old station urls (`/stations/*.html`) redirect to the new pages, so coaches' home-screen bookmarks keep working.
5. Remove the old app's files and, if no other vhost uses it, PHP from the host (`dnf remove php*`).
6. Photos taken before the cutover keep their old S3 names (`original-player-image-<bib>.jpg`). New rows use `player_img`, so start the season with an empty `evaluations` table.

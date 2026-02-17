# Staging Runbook (ECS + Queue + Email Verify)

## Objetivo
Evitar incidentes por tasks huérfanas, workers con config vieja, o llaves Passport inconsistentes.

## Reglas de operación
1. No usar `aws ecs run-task` para API en staging.
2. Desplegar solo por `deploy_st` (GitHub Actions) sobre `staging`.
3. No regenerar llaves Passport en deploy (`passport:install` / `passport:keys --force` prohibido en runtime).
4. Si un deploy falla sanity checks, no continuar con pruebas funcionales hasta corregir.

## Checklist post-deploy (manual rápido)
1. Ver task definition activa del servicio API:
```bash
aws ecs describe-services \
  --cluster tike-api-staging \
  --services tike-api-staging-service \
  --query 'services[0].taskDefinition' \
  --output text
```
2. Ver tasks running y grupos:
```bash
aws ecs list-tasks --cluster tike-api-staging --desired-status RUNNING --output text
aws ecs describe-tasks --cluster tike-api-staging --tasks $(aws ecs list-tasks --cluster tike-api-staging --desired-status RUNNING --query 'taskArns' --output text) \
  --query 'tasks[].{group:group,taskDef:taskDefinitionArn,task:taskArn}' --output table
```
3. Confirmar que NO exista `group = family:tike-api-staging`.
4. Deben existir solo tasks de:
- `service:tike-api-staging-service`
- `service:tike-web-staging-service`

## Respuesta a incidente (10 minutos)
1. Detectar task huérfana:
```bash
aws ecs describe-tasks --cluster tike-api-staging --tasks $(aws ecs list-tasks --cluster tike-api-staging --desired-status RUNNING --query 'taskArns' --output text) \
  --query 'tasks[?group==`family:tike-api-staging`].taskArn' --output text
```
2. Detener task huérfana (si existe):
```bash
aws ecs stop-task --cluster tike-api-staging --task <TASK_ARN> --reason "Stopping orphan standalone task"
```
3. Forzar redeploy limpio del servicio API:
```bash
aws ecs update-service \
  --cluster tike-api-staging \
  --service tike-api-staging-service \
  --force-new-deployment

aws ecs wait services-stable \
  --cluster tike-api-staging \
  --services tike-api-staging-service
```
4. Repetir checklist post-deploy.

## Señales de problema conocidas
1. Correos duplicados con URLs distintas (`http` y `https`).
2. `401 Unauthenticated` en `/api/v1/auth/verify-email` con token recién emitido.
3. Logs con `Invalid key supplied` o problemas de `oauth-private.key`.

## Automatización existente
`deploy_st` ejecuta `scripts/staging_sanity_check.sh` después de `services-stable` para:
1. verificar rollout completo,
2. validar que el servicio corra la task definition recién registrada,
3. bloquear tasks huérfanas `family:tike-api-staging`.

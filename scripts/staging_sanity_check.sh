#!/usr/bin/env bash
set -euo pipefail

: "${ECS_CLUSTER:?ECS_CLUSTER is required}"
: "${ECS_SERVICE:?ECS_SERVICE is required}"
: "${ECS_TASK_FAMILY:?ECS_TASK_FAMILY is required}"

EXPECTED_TASK_DEF_ARN="${EXPECTED_TASK_DEF_ARN:-}"
WEB_SERVICE_NAME="${WEB_SERVICE_NAME:-tike-web-staging-service}"

echo "[sanity] Checking ECS service state for ${ECS_SERVICE} in ${ECS_CLUSTER}"

SERVICE_DESC_JSON="$(aws ecs describe-services \
  --cluster "${ECS_CLUSTER}" \
  --services "${ECS_SERVICE}" \
  --output json)"

DESIRED_COUNT="$(echo "${SERVICE_DESC_JSON}" | jq -r '.services[0].desiredCount')"
RUNNING_COUNT="$(echo "${SERVICE_DESC_JSON}" | jq -r '.services[0].runningCount')"
ACTIVE_DEPLOYMENTS="$(echo "${SERVICE_DESC_JSON}" | jq -r '[.services[0].deployments[] | select(.status == "PRIMARY" and .rolloutState != "COMPLETED")] | length')"
SERVICE_TASK_DEF="$(echo "${SERVICE_DESC_JSON}" | jq -r '.services[0].taskDefinition')"

if [[ "${DESIRED_COUNT}" -lt 1 ]]; then
  echo "[sanity] ERROR: ${ECS_SERVICE} desiredCount is ${DESIRED_COUNT}. Must be at least 1."
  exit 1
fi

if [[ "${RUNNING_COUNT}" != "${DESIRED_COUNT}" ]]; then
  echo "[sanity] ERROR: ${ECS_SERVICE} runningCount=${RUNNING_COUNT}, desiredCount=${DESIRED_COUNT}."
  exit 1
fi

if [[ "${ACTIVE_DEPLOYMENTS}" != "0" ]]; then
  echo "[sanity] ERROR: ${ECS_SERVICE} still has PRIMARY deployment in progress."
  exit 1
fi

if [[ -n "${EXPECTED_TASK_DEF_ARN}" && "${SERVICE_TASK_DEF}" != "${EXPECTED_TASK_DEF_ARN}" ]]; then
  echo "[sanity] ERROR: ${ECS_SERVICE} task definition mismatch."
  echo "[sanity] Expected: ${EXPECTED_TASK_DEF_ARN}"
  echo "[sanity] Current : ${SERVICE_TASK_DEF}"
  exit 1
fi

echo "[sanity] Checking for orphan standalone tasks in cluster ${ECS_CLUSTER}"

RUNNING_TASK_ARNS="$(aws ecs list-tasks \
  --cluster "${ECS_CLUSTER}" \
  --desired-status RUNNING \
  --query 'taskArns' \
  --output text || true)"

if [[ -z "${RUNNING_TASK_ARNS}" || "${RUNNING_TASK_ARNS}" == "None" ]]; then
  echo "[sanity] ERROR: no running tasks found in cluster ${ECS_CLUSTER}."
  exit 1
fi

TASKS_JSON="$(aws ecs describe-tasks \
  --cluster "${ECS_CLUSTER}" \
  --tasks ${RUNNING_TASK_ARNS} \
  --output json)"

ORPHAN_TASKS="$(echo "${TASKS_JSON}" | jq -r --arg family "${ECS_TASK_FAMILY}" '.tasks[] | select(.group == ("family:" + $family)) | .taskArn')"
if [[ -n "${ORPHAN_TASKS}" ]]; then
  echo "[sanity] ERROR: found orphan standalone tasks for family ${ECS_TASK_FAMILY}:"
  echo "${ORPHAN_TASKS}"
  exit 1
fi

API_SERVICE_TASKS="$(echo "${TASKS_JSON}" | jq -r --arg svc "${ECS_SERVICE}" '[.tasks[] | select(.group == ("service:" + $svc))] | length')"
if [[ "${API_SERVICE_TASKS}" -lt 1 ]]; then
  echo "[sanity] ERROR: no running tasks for service:${ECS_SERVICE}."
  exit 1
fi

WEB_SERVICE_TASKS="$(echo "${TASKS_JSON}" | jq -r --arg svc "${WEB_SERVICE_NAME}" '[.tasks[] | select(.group == ("service:" + $svc))] | length')"
if [[ "${WEB_SERVICE_TASKS}" -lt 1 ]]; then
  echo "[sanity] ERROR: no running tasks for service:${WEB_SERVICE_NAME}."
  exit 1
fi

echo "[sanity] ECS checks passed."

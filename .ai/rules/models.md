---
paths:
  - 'app/{Models/Optimisation*.php,Services/*Deployment*.php,Contracts/DeploymentDriver.php}'
---

# Models

## Keep optimisation history append-only
Optimisation is the stable deployment intent. Store each content revision in a new numbered OptimisationVersion and every deploy/rollback attempt in OptimisationDeployment tied to that exact version. A live optimisation must be rolled back before revision; never overwrite or delete its value/deployment history.

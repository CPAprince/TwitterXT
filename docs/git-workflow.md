<!-- File: docs/git-workflow.md -->

# Git Workflow

This project follows a simplified **Git Flow** model.
See the full [original Git Flow explanation](https://nvie.com/posts/a-successful-git-branching-model).

## Branches

| Branch      | Purpose                            | Direct commits? | Merge target     |
|-------------|------------------------------------|-----------------|------------------|
| `main`      | Production (always deployable)     | No              | ← develop        |
| `develop`   | Current development / next release | No              | ← feature/*      |
| `feature/*` | Individual tasks                   | Yes             | → develop        |
| `hotfix/*`  | Urgent production fixes            | Yes             | → main + develop |
| `release/*` | Release preparation (rarely used)  | Yes             | → main + develop |

## Naming conventions

- Branches: `feature/JIRA-TICKET-KEY-short-description`  
  Example: `feature/TK-23-user-profile-edit`

## Commit messages

Follow the [Conventional Commits](https://www.conventionalcommits.org)
format with a Jira ticket key included, for example:

```text
feat(auth): TK-18 Add user registration endpoint
fix(search: TK-19 Prevent SQL injection in search
refactor(auth): TK-23 Extract authentication service
docs(api): TK-28 Update API documentation
test(auth): TK-20 Add tests for password reset
```

### Commit message template

To use the [message template](../.github/.gitmessage), change Git config local settings:

```shell
git config --local commit.template .github/.gitmessage
```

## Prohibited

- Direct commits or force-push to `main` and `develop`
- Merging PRs without review
- Working without a feature branch

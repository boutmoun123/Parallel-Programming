$ErrorActionPreference = 'Stop'

Set-Location -LiteralPath $PSScriptRoot

function Run-TestStep {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Title,

        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    Write-Host ""
    Write-Host ("=" * 72) -ForegroundColor DarkCyan
    Write-Host $Title -ForegroundColor Cyan
    Write-Host ("=" * 72) -ForegroundColor DarkCyan

    & php @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Step failed: $Title"
    }
}

try {
    Run-TestStep -Title "Full Project Test Suite" -Arguments @(
        'artisan',
        'test'
    )

    Run-TestStep -Title "Requirement 1 - Concurrent Access and Data Integrity" -Arguments @(
        'artisan',
        'test',
        'tests/Feature/OrderCheckoutConcurrencyTest.php'
    )

    Run-TestStep -Title "Requirement 2 - Resource Management and Capacity Control" -Arguments @(
        'artisan',
        'test',
        'tests/Feature/CapacityControlTest.php'
    )

    Run-TestStep -Title "Requirement 3 - Asynchronous Queues" -Arguments @(
        'artisan',
        'test',
        'tests/Feature/QueuedPostPaymentWorkTest.php'
    )

    Run-TestStep -Title "Requirement 4 - Batch Processing" -Arguments @(
        'artisan',
        'test',
        'tests/Feature/DailySalesReportBatchProcessingTest.php'
    )

    Run-TestStep -Title "Requirement 5 - Load Distribution" -Arguments @(
        'artisan',
        'test',
        'tests/Feature/LoadDistributionTest.php'
    )

    Write-Host ""
    Write-Host "All code checks and requirement 1-5 tests passed successfully." -ForegroundColor Green
    exit 0
}
catch {
    Write-Host ""
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

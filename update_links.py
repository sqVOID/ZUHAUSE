
import os

files = [
    "voidsales.php", "useractivation.php", "upgradeunit.php", "stocktransfer.php", 
    "salesentry.php", "report.php", "replacementunit.php", "refund.php", 
    "rddelivery.php", "purchaseorder.php", "promotereg.php", "preorder.php", 
    "itemreg.php", "groupreg.php", "familycodereg.php", "dsentry.php", 
    "departmentreg.php", "dealerregistration.php", "claimitem.php", 
    "brandReg.php", "branchregistration.php", "accountregistration.php"
]

base_dir = r"c:\xampp\htdocs\IMS"

for filename in files:
    filepath = os.path.join(base_dir, filename)
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Replace only the logout link href
        new_content = content.replace('href="login.php"', 'href="logout.php"')
        
        if content != new_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"Updated {filename}")
        else:
            print(f"No changes needed for {filename}")
            
    except Exception as e:
        print(f"Error updating {filename}: {e}")

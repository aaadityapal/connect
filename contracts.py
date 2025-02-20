import datetime
from num2words import num2words

def generate_contract(sequence_number):
    # Get current date and format it
    current_date = datetime.datetime.now()
    formatted_date = current_date.strftime("%d/%m/%Y")
    year = current_date.strftime("%Y")
    month = current_date.strftime("%m")

    # Generate reference number
    ref_no = f"AH/{year}/{month}/{sequence_number:03d}"

    # Collecting information from the user
    date = input(f"Enter Date (DD/MM/YYYY) [{formatted_date}]: ") or formatted_date
    client_name = input("Enter Client's Full Name: ")
    father_name = input("Enter Father's Name: ")
    permanent_address = input("Enter Permanent Address: ")
    site_address = input("Enter Site Address: ")
    mobile_number = input("Enter Mobile Number: ")
    email_address = input("Enter Email Address: ")
    location = input("Enter Project Location: ")
    area_sq_ft = input("Enter Area in sq.ft: ")
    num_bedrooms = input("Enter Number of Bedrooms: ")
    num_dining_rooms = input("Enter Number of Dining Rooms: ")
    num_living_areas = input("Enter Number of Living Areas: ")
    additional_features = input("Enter Additional Rooms and Features: ")
    amount = input("Enter Consultancy Fee Amount: ")
    amount_in_words = num2words(amount, lang='en', to='currency')
    trademark_name = input("Enter Trademark Name: ")

    # Fixed account details from the image
    account_holder_name = "Prabhat Arya"
    account_number = "03101614505"
    ifsc_code = "ICIC0000031"
    bank_name = "ICICI Bank, Sector 18 Noida, Branch."
    gpay_number = "9958600397"

    # Generating the contract with a logo placeholder
    contract = f"""
    {{logo_placeholder:Right}}
    Ref. No.: {ref_no}
    Date: {date}

    Client:
    Client's Name: {client_name}
    Father's Name: {father_name}
    Permanent Address: {permanent_address}
    Site Address: {site_address}
    Mobile No.: {mobile_number}
    E-mail ID: {email_address}

    Subject: Consultancy fee as discussed over call
    Project: Interior and Designing of Residence at {location}
    Scope of Work: Interior and designing Residential Flat (area {area_sq_ft} sq.ft) having {num_bedrooms} Bedroom(s), {num_dining_rooms} Dining Area, {num_living_areas} Living Area, {additional_features} as per client's requirement.

    Consultancy Fees:
    1. INR {amount} (Rupees {amount_in_words} Only).
    2. GST shall be applicable as per Govt. Norms included in Consultancy Fees.

    Kindly refer to the Accounts Details:
    Account Holder Name: {account_holder_name}
    Account Number: {account_number}
    IFSC Code: {ifsc_code}
    Bank Name: {bank_name}
    GPay Number: {gpay_number}

    Please note:
    1. Overall Architectural changes shall be in the scope of M/S ArchitectsHive, if any.
    2. M/S ArchitectsHive shall work under the trademark for M/s. {trademark_name}.
    3. Drawings shall be provided in feet inches as per the accurate measurements oversite.
    4. Only PDF (Soft Copy) shall be shared via Mail or Whatsapp to reduce the usage of paper and to save the environment.

    Page 1 of 1
    """

    # Output the contract to a text file
    with open("contract.txt", "w") as file:
        file.write(contract)

    print("Contract generated successfully and saved to 'contract.txt'.")
    return sequence_number + 1

if __name__ == "__main__":
    # Initialize or load the sequence number
    sequence_number = 1  # This should be loaded from a persistent storage if available
    sequence_number = generate_contract(sequence_number)
    # Save the updated sequence number to a persistent storage

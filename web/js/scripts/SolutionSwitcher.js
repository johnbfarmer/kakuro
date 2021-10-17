import React from 'react';
import {Button, ButtonToolbar} from 'react-bootstrap';

function SolutionSwitcher(props) {
    if (props.solution.length === 0) {
        return <div></div>;
    }
    return (
        <div>
            <ButtonToolbar>
                <Button onClick={props.showAlternate} bsStyle="primary">Show Other Solution</Button>
            </ButtonToolbar>
        </div>
    );
}

module.exports = SolutionSwitcher;

import React from 'react';
import { ButtonGroup, Button, Glyphicon } from 'react-bootstrap';

export default class KakuroControls extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            name: ''
        };

        this.loadVals(props);

        this.updateName = this.updateName.bind(this);
        this.save = this.save.bind(this);
    }

    componentDidMount() {}

    componentWillUpdate(props) {
        this.loadVals(props);
    }

    loadVals(props) {
        this.state.name = props.name;
    }

    updateName(e) {
        var val = e.target.value;
        this.setState({name: val});
    }

    save() {
        this.props.save(this.state.name);
    }

    render() {
        return (
            <div>
                <input value={this.state.name} onChange={this.updateName} />
                <ButtonGroup>
                    <Button onClick={this.save} title="save">
                        <Glyphicon glyph="floppy-disk" />
                    </Button>
                </ButtonGroup>
            </div>
        );
    }
}

